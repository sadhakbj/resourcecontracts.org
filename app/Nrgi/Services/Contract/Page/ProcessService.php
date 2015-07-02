<?php namespace App\Nrgi\Services\Contract\Page;

use App\Nrgi\Mail\MailQueue;
use App\Nrgi\Services\Contract\ContractService;
use Carbon\Carbon;
use Illuminate\Contracts\Filesystem\Factory as Storage;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Contracts\Logging\Log;
use Symfony\Component\Process\Process;

/**
 * Use for processing pages
 * Class ProcessService
 *
 * @package App\Nrgi\Services\Contract\Page
 */
class ProcessService
{
    /**
     * @var Filesystem
     */
    protected $fileSystem;

    /**
     * @var ContractService
     */
    protected $contract;

    /**
     * @var PageService
     */
    protected $page;

    /**
     * @var Log
     */
    protected $logger;
    /**
     * @var MailQueue
     */
    protected $mailer;

    /**
     * @param Filesystem      $fileSystem
     * @param ContractService $contract
     * @param PageService     $page
     * @param Storage         $storage
     * @param Log             $logger
     * @param MailQueue       $mailer
     */
    public function __construct(
        Filesystem $fileSystem,
        ContractService $contract,
        PageService $page,
        Storage $storage,
        Log $logger,
        MailQueue $mailer
    ) {
        $this->fileSystem = $fileSystem;
        $this->contract   = $contract;
        $this->page       = $page;
        $this->storage    = $storage;
        $this->logger     = $logger;
        $this->mailer     = $mailer;
    }

    /**
     * @param $contractId
     * @return bool
     */
    public function execute($contractId)
    {
        $startTime = Carbon::now();
        try {
            $contract  = $this->contract->find($contractId);
            $this->logger->info("processing Contract", ['contractId' => $contractId]);
            list($writeFolderPath, $readFilePath) = $this->setup($contract);

            if ($this->process($writeFolderPath, $readFilePath)) {
                $pages = $this->page->buildPages($writeFolderPath);
                $this->page->savePages($contractId, $pages);
                $this->updateContractPdfStructure($contract, $writeFolderPath);
                $this->logger->info("processing contract completed.", ['contractId' => $contractId]);
                $this->mailer->send(
                    [
                        'email' => $contract->created_user->email,
                        'name'  => $contract->created_user->name
                    ],
                    "{$contract->title} processing contract completed.",
                    'emails.process_success',
                    [
                        'contract_title' => $contract->title,
                        'start_time'     => $startTime->toDayDateTimeString(),
                        'end_time'       => Carbon::now()->toDayDateTimeString()
                    ]
                );

                return true;
            }
        } catch (\Exception $e) {
            $this->mailer->send(
                [
                    'email' => $contract->created_user->email,
                    'name'  => $contract->created_user->name
                ],
                "{$contract->title} processing error.",
                'emails.process_error',
                [
                    'contract_title' => $contract->title,
                    'start_time'     => $startTime->toDayDateTimeString(),
                    'error'       => $e->getMessage()
                ]
            );
            $this->logger->error("error processing contract.{$e->getMessage()}", ['contractId' => $contractId]);

            return false;
        }

        return false;
    }

    /**
     * @param $contract
     * @param $writeFolderPath
     * @return mixed
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function updateContractPdfStructure($contract, $writeFolderPath)
    {
        $content                 = $this->fileSystem->get(sprintf('%s/stats.json', $writeFolderPath));
        $data                    = json_decode($content);
        $contract->pdf_structure = strtolower($data->status);

        return $contract->save();
    }

    /**
     * @param $writeFolderPath
     * @param $readFilePath
     * @return bool|null
     */
    public function process($writeFolderPath, $readFilePath)
    {
        try {
            $this->processStatus($writeFolderPath, 0);
            $this->processContractDocument($writeFolderPath, $readFilePath);
            $this->processStatus($writeFolderPath, 1);
            $this->logger->info("processing contract completed");

            return true;
        } catch (\Exception $e) {
            $this->logger->error(
                sprintf('error.%s', $e->getMessage()),
                [
                    'write_folder_path' => $writeFolderPath,
                    'read_file_path'    => $readFilePath
                ]
            );

            return false;
        }
    }

    /**
     * @param $writeFolderPath
     * @param $readFilePath
     * @return bool
     */
    public function processContractDocument($writeFolderPath, $readFilePath)
    {
        set_time_limit(0);
        $commandPath = config('nrgi.pdf_process_path');
        $command     = sprintf('python %s/run.py -i %s -o %s', $commandPath, $readFilePath, $writeFolderPath);
        $this->logger->info("processing command", ['command' => $command]);
        $process = new Process($command);
        $process->setTimeout(360 * 10);
        $process->start();
        while ($process->isRunning()) {
            echo $process->getIncrementalOutput();
        }
        if (!$process->isSuccessful()) {
            //todo remove folder
            $this->logger->error("error while executing command.{$process->getErrorOutput()}", ['command' => $command]);
            throw new \RuntimeException($process->getErrorOutput());
        }

        return true;
    }

    /**
     * @param $contractId
     * @return bool
     */
    public function checkIfProcessed($contractId)
    {
        return file_exists($this->getContractDirectory($contractId));
    }

    /**
     * @param $directory
     * @param $path
     * @throws \Exception
     */
    public function addDirectory($directory, $path)
    {
        if (!$this->fileSystem->makeDirectory($path . '/' . $directory, 0777, true)) {
            $this->logger->error(sprintf("error while creating director.%s/%s", $path, $directory));
            throw new \Exception(sprintf('could not make directory.%s/%s'), $path, $directory);
        }
    }

    /**
     * @param $contract
     * @return array
     * @throws \Exception
     */
    public function setup($contract)
    {
        $this->logger->info('Download started...', ['file' => $contract->file]);
        try {
            $pdfFile = $this->storage->disk('s3')->get($contract->file);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage(), ['contract id' => $contract->id, 'file' => $contract->file]);
        }
        $this->storage->disk('local')->put($contract->file, $pdfFile);
        $this->logger->info('Download completed...', ['file' => $pdfFile]);

        if (!$this->fileSystem->isDirectory($this->getContractDirectory($contract->id))) {
            $this->addDirectory($contract->id, $this->getWriteDirectory());
        }

        $writeFolderPath = $this->getContractDirectory($contract->id);
        $readFilePath    = sprintf('%s/app/%s', storage_path(), $contract->file);

        return array($writeFolderPath, $readFilePath);
    }

    /**
     * @param $directory
     * @param $status
     * @throws \Exception
     */
    public function processStatus($directory, $status)
    {
        $fileContent = $status . PHP_EOL;
        $filePath    = sprintf('%s/status.txt', $directory);
        $this->logger->info("writing to {$filePath}", ['status' => $status]);
        if (!$this->fileSystem->put($filePath, $fileContent)) {
            $this->logger->error("could not create status file in directory {$directory}");
            throw new \Exception("could not create status file.");
        }
    }

    /**
     * @param $contractId
     * @return string
     */
    public function getContractDirectory($contractId)
    {
        return sprintf('%s/%s', $this->getWriteDirectory(), $contractId);
    }

    /**
     * provides write folder path
     *
     * @return string
     */
    public function getWriteDirectory()
    {
        $publicPath = public_path();

        return sprintf('%s/%s', $publicPath, 'data');
    }
}

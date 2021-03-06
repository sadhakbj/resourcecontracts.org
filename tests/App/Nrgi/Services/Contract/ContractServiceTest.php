<?php use App\Nrgi\Services\Contract\ContractService;
use Tests\NrgiTestCase;

use \Mockery as m;

class ContractServiceTest extends NrgiTestCase
{
    protected $contractRepository;
    protected $auth;
    protected $storage;
    protected $uploadedFile;
    protected $contractService;
    protected $filesystem;
    protected $countryService;
    protected $queue;

    public function setup()
    {
        $this->markTestSkipped();
        parent::setUp();
        $this->contractRepository = m::mock('App\Nrgi\Repositories\Contract\ContractRepositoryInterface');
        $this->auth               = m::mock('Illuminate\Auth\Guard');
        $this->storage            = m::mock('Illuminate\Contracts\Filesystem\Factory');
        $this->filesystem         = m::mock('Illuminate\Filesystem\Filesystem');
        $this->uploadedFile       = m::mock('Symfony\Component\HttpFoundation\File\UploadedFile');
        $this->countryService     = m::mock('App\Nrgi\Services\Contract\CountryService');
        $this->queue              = m::mock('Illuminate\Contracts\Queue\Queue');
        $this->log                = m::mock('Illuminate\Contracts\Logging\Log');

        $this->contractService = new ContractService(
            $this->contractRepository,
            $this->auth,
            $this->storage,
            $this->filesystem,
            $this->countryService,
            $this->queue,
            $this->log
        );

        $this->formData = [
            "contract_name"              => '',
            "contract_identifier"        => '',
            "language"                  => '',
            "country"                   => '',
            "resource"                  => '',
            "government_entity"         => '',
            "government_identifier"     => '',
            "type_of_contract"          => '',
            "signature_date"            => '',
            "signature_year"            => '',
            "document_type"             => '',
            "translation_from_original" => '',
            "translation_parent"        => '',
            "company"                   => '',
            "license_name"              => '',
            "license_identifier"        => '',
            "project_title"             => '',
            "project_identifier"        => '',
            "Source_url"                => '',
            "date_retrieval"            => '',
            "category"                  => ''
        ];
    }

    public function testItShouldGetContractModel()
    {
        $contractModel = 'App\Nrgi\Entities\Contract\Contract';
        $this->contractRepository->shouldReceive('findContract')->once()->with(1)->andReturn(m::mock($contractModel));
        $this->assertInstanceOf($contractModel, $this->contractService->find(1));
    }

    public function testItShouldSaveContract()
    {
        $this->markTestSkipped();

        $user = m::mock('App\Nrgi\Entities\User\User');
        $this->auth->shouldReceive('user')->once()->andReturn($user);
        $user->shouldReceive('getAttribute')->once()->with('id')->andReturn(1);
        $this->uploadedFile->shouldReceive('isValid')->once()->andReturn(true);
        $this->uploadedFile->shouldReceive('getClientOriginalName')->once()->andReturn('file');
        $this->uploadedFile->shouldReceive('getClientOriginalExtension')->once()->andReturn('pdf');
        $this->uploadedFile->shouldReceive('getSize')->once()->andReturn('filesize');
        $this->storage->shouldReceive('disk->put')->once()->andReturn(true);
        $this->filesystem->shouldReceive('get')->once()->with($this->uploadedFile)->andReturn('file');
        $contract = m::mock('App\Nrgi\Entities\Contract\Contract');
        $contract->shouldReceive('getAttribute')->once()->with('id')->andReturn(1);
        $this->countryService->shouldReceive('getInfoByCode')->once()->with('')->andReturn('');

        $this->contractRepository->shouldReceive('save')->once()->andReturn($contract);

//        $exception = m::mock('App\Agentcis\Exceptions\ModelNotSavedException');
        //       $exception->shouldReceive('getMessage')->andReturn('');
        $data=[];
        $data['metadata'] = $this->formData;
        $this->log->shouldReceive('info')->once()->with("Contract successfully created.", array('ContractTitle' => $data['metadata']['project_title']));
        // $this->log->shouldReceive('error')->once()->with('');

        $this->queue->shouldReceive('push')->once()->with(
            'App\Nrgi\Services\Queue\ProcessDocumentQueue',
            ['contract_id' => 1]
        )->andReturn('');

        $this->formData['file']      = $this->uploadedFile;
        $this->formData['file_size'] = 'filesize';
        $this->assertInstanceOf(
            'App\Nrgi\Entities\Contract\Contract',
            $this->contractService->saveContract($this->formData)
        );
    }

    public function testItShouldNotSaveContractWhenInvalidFile()
    {
        $this->uploadedFile->shouldReceive('isValid')->once()->andReturn(false);
        $this->formData['file'] = $this->uploadedFile;
        $this->assertFalse($this->contractService->saveContract($this->formData));
    }

    public function testItShouldNotSaveContractWhenFileNotUploadedOnS3()
    {
        $user = m::mock('App\Nrgi\Entities\User\User');
        $this->uploadedFile->shouldReceive('isValid')->once()->andReturn(true);
        $this->uploadedFile->shouldReceive('getClientOriginalName')->once()->andReturn('file');
        $this->uploadedFile->shouldReceive('getClientOriginalExtension')->once()->andReturn('pdf');
        $this->storage->shouldReceive('disk->put')->once()->andReturn(false);
        $this->filesystem->shouldReceive('get')->once()->with($this->uploadedFile)->andReturn('file');
        $this->formData['file'] = $this->uploadedFile;
        $this->assertFalse($this->contractService->saveContract($this->formData));
    }

    public function testItShouldDeleteContract()
    {
        $this->markTestSkipped();

        $contract = m::mock('App\Nrgi\Entities\Contract\Contract');
        $this->contractRepository->shouldReceive('findContract')->once()->with(1)->andReturn($contract);
        $contract->shouldReceive('getAttribute')->once()->with('id')->andReturn(1);
        $contract->shouldReceive('getAttribute')->once()->with('file')->andReturn('file');
        $this->storage->shouldReceive('disk->exists')->once()->with('file')->andReturn(true);
        $this->contractRepository->shouldReceive('delete')->once()->with(1)->andReturn(true);
        $this->storage->shouldReceive('disk->delete')->once()->with('file')->andReturn(true);
        $this->assertTrue($this->contractService->deleteContract(1));
    }

    public function testItShouldNotDeleteContractWhenCantRemoveFromDB()
    {
        $this->markTestSkipped();

        $contract = m::mock('App\Nrgi\Entities\Contract\Contract');
        $this->contractRepository->shouldReceive('findContract')->once()->with(1)->andReturn($contract);
        $contract->shouldReceive('getAttribute')->once()->with('id')->andReturn(1);
        $this->contractRepository->shouldReceive('delete')->once()->with(1)->andReturn(false);
        $this->assertFalse($this->contractService->deleteContract(1));
    }

    public function testItShouldUpdateContract()
    {
        $this->markTestSkipped();

        $contract = m::mock('App\Nrgi\Entities\Contract\Contract');
        $this->contractRepository->shouldReceive('findContract')->once()->with('1')->andReturn($contract);
        $contract->shouldReceive('save')->once()->andReturn(true);
        $data                        = new stdClass();
        $data->file_size             = 'size';
        $this->formData['file_size'] = 'size';
        $contract->shouldReceive('getAttribute')->once()->with('metadata')->andReturn($data);
        $contract->shouldReceive('setAttribute')->once()->with('metadata', $this->formData)->andReturn([]);
        $this->countryService->shouldReceive('getInfoByCode')->once()->with('')->andReturn('');
        $this->assertTrue($this->contractService->updateContract(1, $this->formData));
    }

    public function testItShouldReturnContractStatusQueue()
    {
        $path = public_path('data/1');
        $this->filesystem->shouldReceive('exists')->once()->with($path)->andReturn(false);
        $this->assertEquals(
            ContractService::CONTRACT_QUEUE,
            $this->contractService->getStatus(1)
        );
    }

    public function testItShouldReturnContractStatusProcessing()
    {
        $path = public_path('data/1');
        $this->filesystem->shouldReceive('exists')->once()->with($path)->andReturn(true);
        $this->filesystem->shouldReceive('get')->once()->with(sprintf('%s/status.txt', $path))->andReturn(
            0
        );

        $this->assertEquals(ContractService::CONTRACT_PENDING, $this->contractService->getStatus(1));
    }

    public function testItShouldReturnContractStatusComplete()
    {
        $path = public_path('data/1');
        $this->filesystem->shouldReceive('exists')->once()->with($path)->andReturn(true);
        $this->filesystem->shouldReceive('get')->once()->with(sprintf('%s/status.txt', $path))->andReturn(
            1
        );

        $this->assertEquals(ContractService::CONTRACT_COMPLETE, $this->contractService->getStatus(1));
    }

    public function tearDown()
    {
        parent::tearDown();
        m::close();
    }
}

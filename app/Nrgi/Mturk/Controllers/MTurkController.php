<?php namespace App\Nrgi\Mturk\Controllers;

use App\Http\Controllers\Controller;
use App\Nrgi\Mturk\Services\ActivityService;
use App\Nrgi\Mturk\Services\TaskService;
use App\Nrgi\Services\Contract\ContractService;
use App\Nrgi\Services\User\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\View;

/**
 * Class MturkController
 * @package App\Nrgi\Mturk\Controllers
 */
class MTurkController extends Controller
{
    /**
     * @var TaskService
     */
    protected $task;
    /**
     * @var ContractService
     */
    protected $contract;
    /**
     * @var ActivityService
     */
    protected $activity;

    /**
     * @param TaskService     $task
     * @param ContractService $contract
     * @param ActivityService $activity
     */
    public function __construct(TaskService $task, ContractService $contract, ActivityService $activity)
    {
        $this->middleware('auth');
        $this->task     = $task;
        $this->contract = $contract;
        $this->activity = $activity;
    }

    /**
     * Display all the contracts sent for MTurk
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $contracts = $this->task->getContracts();

        return view('mturk.index', compact('contracts'));
    }

    /**
     * Display all the tasks for a specific contract
     *
     * @return string
     */
    public function tasksList($contract_id)
    {
        $contract        = $this->contract->findWithTasks($contract_id);
        $contract->tasks = $this->task->appendAssignment($contract->tasks);
        $total_pages     = $contract->tasks->count();
        $total_hit       = $this->task->getTotalHits($contract_id);
        $status          = $this->task->getTotalByStatus($contract_id);

        return view('mturk.tasks', compact('contract', 'total_pages', 'total_hit', 'status'));
    }

    /**
     * Create tasks
     *
     * @param $id
     * @return Redirect
     */
    public function createTasks($id)
    {
        if ($this->task->create($id)) {
            return redirect()->back()->withSuccess(trans('mturk.action.sent_to_mturk'));
        }

        return redirect()->back()->withError(trans('mturk.action.sent_fail_to_mturk'));
    }

    /**
     * Task Detail
     *
     * @param $contract_id
     * @param $task_id
     * @return \Illuminate\View\View
     */
    public function taskDetail($contract_id, $task_id)
    {
        $contract = $this->contract->findWithTasks($contract_id);
        $task     = $this->task->get($contract_id, $task_id);

        return view('mturk.detail', compact('contract', 'task'));
    }

    /**
     * Approve Assignment
     *
     * @param $contract_id
     * @param $task_id
     * @return Redirect
     */
    public function approve($contract_id, $task_id)
    {
        if ($this->task->approveTask($contract_id, $task_id)) {
            return redirect()->back()->withSuccess(trans('mturk.action.approve'));
        }

        return redirect()->back()->withError(trans('mturk.action.approve_fail'));
    }

    /**
     * Reject Assignment
     *
     * @param         $contract_id
     * @param         $task_id
     * @param Request $request
     * @return Redirect
     */
    public function reject($contract_id, $task_id, Request $request)
    {
        $message = $request->input('message');

        if ($message == '') {
            return redirect()->back()->withError(trans('mturk.action.reject_reason'));
        }

        if ($this->task->rejectTask($contract_id, $task_id, $message)) {
            return redirect()->back()->withSuccess(trans('mturk.action.rejected'));
        }

        return redirect()->back()->withError(trans('mturk.action.reject_fail'));
    }

    /**
     * Reset HIT
     *
     * @param $contract_id
     * @param $task_id
     * @return Redirect
     */
    public function resetHit($contract_id, $task_id)
    {
        if ($this->task->resetHIT($contract_id, $task_id)) {
            return redirect()->back()->withSuccess(trans('mturk.action.reset'));
        }

        return redirect()->back()->withError(trans('mturk.action.reset_fail'));
    }

    /**
     * Sent text to RC
     *
     * @param $contract_id
     * @return Redirect
     */
    public function sendToRC($contract_id)
    {
        if ($this->task->copyTextToRC($contract_id)) {
            return redirect()->back()->withSuccess(trans('mturk.action.sent_to_rc'));
        }

        return redirect()->back()->withError(trans('mturk.action.sent_fail_to_rc'));
    }

    /**
     * @param Request     $request
     * @param UserService $user
     * @return View
     */
    public function activity(Request $request, UserService $user)
    {
        $filter     = $request->only('contract', 'user');
        $activities = $this->activity->getAll($filter);
        $users      = $user->getList();
        $contracts  = $this->task->getContractsList();

        return view('mturk.activity', compact('activities', 'users', 'contracts'));
    }

}

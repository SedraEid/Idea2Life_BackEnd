<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\GanttChart;
use App\Models\Idea;
use App\Models\Meeting;
use App\Models\Notification;
use App\Models\Report;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    /**
     *  عرض كل التاسكات ضمن مرحلة معينة (gantt_id)
     */
    public function index(Request $request, $gantt_id)
    {
        $user = $request->user();
        $gantt = GanttChart::with('idea.ideaowner')->findOrFail($gantt_id);

        if (!$gantt->idea || !$gantt->idea->ideaowner || $gantt->idea->ideaowner->user_id != $user->id) {
            return response()->json(['message' => 'لا يمكنك عرض هذه المرحلة لأنها لا تخصك.'], 403);
        }

        $tasks = Task::where('gantt_id', $gantt_id)->get();

        return response()->json([
            'message' => 'تم جلب جميع المهام الخاصة بهذه المرحلة بنجاح',
            'data' => $tasks
        ]);
    }

    /**
     *  إنشاء تاسك جديد داخل مرحلة (gantt_id)
     */
    public function store(Request $request, $gantt_id)
    {
        $user = $request->user();

        $gantt = GanttChart::with('idea.ideaowner')->findOrFail($gantt_id);

        if (!$gantt->idea || !$gantt->idea->ideaowner || $gantt->idea->ideaowner->user_id != $user->id) {
            return response()->json(['message' => 'لا يمكنك إضافة مهام إلى هذه المرحلة لأنها لا تخصك.'], 403);
        }
        
    $hasPendingImprovementPlan = $gantt->idea->improvementPlans()
        ->where('status', 'pending')
        ->exists();

    if ($hasPendingImprovementPlan) {
        return response()->json([
            'message' => 'لا يمكنك إضافة مهام جديدة حالياً. هناك خطة تحسين معلقة يجب إعدادها ورفعها لإقناع اللجنة بجدّيتك في تطوير الفكرة.'
        ], 403);
    }

        $request->validate([
            'task_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'priority' => 'nullable|integer|min:1',
        ]);

        $task = Task::create([
            'idea_id' => $gantt->idea->id,
            'gantt_id' => $gantt_id,
            'owner_id' => $gantt->idea->ideaowner->id,
            'task_name' => $request->task_name,
            'description' => $request->description,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'priority' => $request->priority ?? 1,
            'status' => 'pending', 
            'progress_percentage' => 0,
        ]);

        return response()->json([
            'message' => 'تم إنشاء المهمة بنجاح',
            'data' => $task
        ], 201);
    }

    /**
     *  تعديل تاسك
     */
 public function update(Request $request, $id)
{
    $user = $request->user();
    $task = Task::with('gantt.idea.ideaowner')->findOrFail($id);

    if (
        !$task->gantt ||
        !$task->gantt->idea ||
        !$task->gantt->idea->ideaowner ||
        $task->gantt->idea->ideaowner->user_id != $user->id
    ) {
        return response()->json(['message' => 'لا يمكنك تعديل هذه المهمة لأنها لا تخصك.'], 403);
    }

    $idea = $task->gantt->idea;
    $hasPendingImprovementPlan = $idea->improvementPlans()
        ->where('status', 'pending')
        ->exists();

    if ($hasPendingImprovementPlan) {
        return response()->json([
            'message' => 'لا يمكنك تعديل نسبة الإنجاز في الوقت الحالي. يجب إعداد ورفع خطة تحسين لإقناع اللجنة بجدّيتك في تطوير الفكرة قبل استكمال العمل.'
        ], 403);
    }

    $previousPhase = GanttChart::where('idea_id', $idea->id)
        ->where('id', '<', $task->gantt->id)
        ->orderBy('id', 'desc')
        ->first();

    if ($previousPhase) {
        $previousReport = Report::where('idea_id', $idea->id)
            ->where('report_type', 'phase_evaluation')
            ->whereHas('meeting', function ($query) use ($previousPhase) {
                $query->where('gantt_chart_id', $previousPhase->id);
            })
            ->first();

        if (!$previousReport) {
            return response()->json([
                'message' => 'لا يمكنك تعديل مهام هذه المرحلة قبل أن يتم تقييم المرحلة السابقة وعقد اجتماع اللجنة الخاص بها.'
            ], 403);
        }
    }

    $approved = $task->gantt->approval_status === 'approved';

    $allFields = ['task_name', 'description', 'start_date', 'end_date', 'priority', 'progress_percentage'];
    $allowedFields = $approved ? ['progress_percentage'] : $allFields;

    $attemptedFields = array_keys($request->all());
    $notAllowedFields = array_diff($attemptedFields, $allowedFields);

    if ($approved && !empty($notAllowedFields)) {
        return response()->json([
            'message' => 'تمت الموافقة على المرحلة من قبل اللجنة، لا يمكنك تعديل هذه الحقول: ' . implode(', ', $notAllowedFields)
        ], 403);
    }

    $request->validate(array_reduce($allowedFields, function ($carry, $field) {
        if ($field === 'progress_percentage') $carry[$field] = 'sometimes|integer|min:0|max:100';
        elseif ($field === 'priority') $carry[$field] = 'sometimes|integer|min:1|max:5';
        elseif ($field === 'start_date') $carry[$field] = 'sometimes|date';
        elseif ($field === 'end_date') $carry[$field] = 'sometimes|date|after_or_equal:start_date';
        else $carry[$field] = 'sometimes|string|max:255';
        return $carry;
    }, []));

    $task->fill($request->only($allowedFields));

    if ($request->has('progress_percentage')) {
        if ($task->progress_percentage == 0) $task->status = 'pending';
        elseif ($task->progress_percentage < 100) $task->status = 'in_progress';
        else $task->status = 'completed';
    }

    $task->save();
$this->updateGanttProgress($task->gantt);


    return response()->json([
        'message' => 'تم تحديث المهمة بنجاح.',
        'task' => $task
    ]);
}




private function updateGanttProgress(GanttChart $gantt)
{
    $tasks = $gantt->tasks()->get();

    if ($tasks->count() === 0) {
        $gantt->progress = 0;
        $gantt->save();
        return;
    }

    $totalWeight = 0;
    $totalProgress = 0;

    foreach ($tasks as $task) {
        $start = Carbon::parse($task->start_date);
        $end = Carbon::parse($task->end_date);

        $duration = $start->diffInDays($end) + 1;
        if ($duration <= 0) $duration = 1;

        $weight = $duration;
        $progress = $task->progress_percentage ?? 0;

        $totalWeight += $weight;
        $totalProgress += ($progress * $weight);
    }

    $gantt->progress = $totalWeight > 0
        ? round($totalProgress / $totalWeight, 2)
        : 0;

     if ($gantt->progress >= 100) {
        $gantt->status = 'completed';
    } else {
        $gantt->status = 'in_progress';
    }

    $gantt->save();
}




    /**
     *  حذف تاسك
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();

        $task = Task::with('gantt.idea.ideaowner')->findOrFail($id);

        if (!$task->gantt || !$task->gantt->idea || !$task->gantt->idea->ideaowner || $task->gantt->idea->ideaowner->user_id != $user->id) {
            return response()->json(['message' => 'لا يمكنك حذف هذه المهمة لأنها لا تخصك.'], 403);
        }

        $task->delete();
$gantt = $task->gantt;
$this->updateGanttProgress($gantt);      
  return response()->json(['message' => 'تم حذف المهمة بنجاح']);
    }










}

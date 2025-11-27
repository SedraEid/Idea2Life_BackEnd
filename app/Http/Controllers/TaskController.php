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

public function update(Request $request, $task_id)
{
    $user = $request->user();
    $task = Task::with('gantt.idea.ideaowner')->findOrFail($task_id);
    $idea = $task->gantt->idea;
    $gantt = $task->gantt;
    if (!$idea || !$idea->ideaowner || $idea->ideaowner->user_id != $user->id) {
        return response()->json(['message' => 'لا يمكنك تعديل هذه المهمة لأنها لا تخصك.'], 403);
    }

    // التحقق من عدد المراحل السيئة
    $badPhasesCount = $idea->ganttCharts()->where('failure_count', 1)->count();
    if ($badPhasesCount >= 3) {
        return response()->json([
            'message' => 'لا يمكنك تعديل المهام حالياً لأن هناك 3 مراحل سيئة. يجب دفع المبلغ الجزائي أو المشروع قد يُلغى.'
        ], 403);
    }

    $validated = $request->validate([
        'progress_percentage' => 'sometimes|numeric|min:0|max:100',
        'attachments' => 'sometimes|array',
        'attachments.*' => 'file|mimes:pdf,jpg,png,docx|max:5120',
    ]);

    if ($request->hasFile('attachments')) {
        $uploadedFiles = [];
        foreach ($request->file('attachments') as $file) {
            $path = $file->store('task_attachments');
            $uploadedFiles[] = $path;
        }

        $existing = $task->attachments;
        if (!is_array($existing)) {
            $existing = $existing ? json_decode($existing, true) : [];
            if (!is_array($existing)) $existing = [];
        }

        $validated['attachments'] = array_merge($existing, $uploadedFiles);
    }

    $task->update($validated);

    return response()->json([
        'message' => 'تم تحديث المهمة بنجاح',
        'data' => $task
    ]);
}





private function updateGanttProgress(GanttChart $gantt)//تعديل نسية الانجاز في كل مرحة حسب التاسكات المرتبطة بالمرحلة 
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
    $gantt = $task->gantt;
    if ($gantt->approval_status === 'approved') {
        return response()->json(['message' => 'لا يمكن حذف المهمة بعد موافقة اللجنة.'], 403);
    }
    $task->delete();
    $this->updateGanttProgress($gantt);      
    return response()->json(['message' => 'تم حذف المهمة بنجاح']);
}
}

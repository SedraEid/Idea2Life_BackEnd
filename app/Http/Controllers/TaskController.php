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
    $gantt = GanttChart::with('idea.owner')->findOrFail($gantt_id);
    if (!$gantt->idea || !$gantt->idea->owner || $gantt->idea->owner->id != $user->id) {
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
    $gantt = GanttChart::with('idea.owner')->findOrFail($gantt_id);
    if (!$gantt->idea || !$gantt->idea->owner || $gantt->idea->owner->id != $user->id) {
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
    $task = Task::with('gantt.idea.owner')->findOrFail($task_id);
    $idea = $task->gantt->idea;
    $gantt = $task->gantt;

    if (!$idea || !$idea->owner || $idea->owner->id != $user->id) {
        return response()->json(['message' => 'لا يمكنك تعديل هذه المهمة لأنها لا تخصك.'], 403);
    }

    $ganttApproved = $idea->ganttCharts()
        ->where('approval_status', 'approved')
        ->exists();

    if (!$ganttApproved) {
        return response()->json([
            'message' => 'لا يمكن تعديل المهمة قبل الموافقة على المرحلة في الـ Gantt Chart.'
        ], 422);
    }

    $badPhasesCount = $idea->ganttCharts()->where('failure_count', 1)->count();
    if ($badPhasesCount >= 3) {
        return response()->json([
            'message' => 'لا يمكنك تعديل المهام حالياً لأن هناك 3 مراحل سيئة. يجب دفع المبلغ الجزائي أو المشروع قد يُلغى.'
        ], 403);
    }

    $validated = $request->validate([
        'task_name' => 'sometimes|string|max:255',
        'description' => 'sometimes|nullable|string',
        'start_date' => 'sometimes|date',
        'end_date' => 'sometimes|date|after_or_equal:start_date',
        'priority' => 'sometimes|integer|min:1',
        'progress_percentage' => 'sometimes|numeric|min:0|max:100',
        'attachments' => 'sometimes|array',
        'attachments.*' => 'file|mimes:pdf,jpg,png,docx|max:5120',
    ]);

    if (isset($validated['start_date']) && $validated['start_date'] < $gantt->start_date) {
        return response()->json(['message' => 'تاريخ بداية المهمة لا يمكن أن يكون قبل بداية المرحلة.'], 422);
    }

    if (isset($validated['end_date']) && $validated['end_date'] > $gantt->end_date) {
        return response()->json(['message' => 'تاريخ نهاية المهمة لا يمكن أن يكون بعد نهاية المرحلة.'], 422);
    }
    if ($request->hasFile('attachments')) {
        $uploadedFiles = [];
        foreach ($request->file('attachments') as $file) {
            $path = $file->storeAs(
                'task_attachments',
                uniqid() . '_' . $file->getClientOriginalName(),
                'public'
            );
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
    $this->updateGanttProgress($gantt);
    $attachmentsWithLinks = [];
    if (!empty($task->attachments)) {
        foreach ($task->attachments as $path) {
            $attachmentsWithLinks[] = asset('storage/' . $path);
        }
    }

    $taskData = $task->toArray();
    $taskData['attachments'] = $attachmentsWithLinks;

    return response()->json([
        'message' => 'تم تحديث المهمة بنجاح',
        'data' => $taskData
    ]);
}

private function updateGanttProgress(GanttChart $gantt)
{
    $tasks = $gantt->tasks()->get();
    if ($tasks->count() === 0) {
        $gantt->progress = 0;
        $gantt->status = 'pending';
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

    $gantt->status = $gantt->progress >= 100 ? 'completed' : 'in_progress';
    $gantt->save();
}

    /**
     *  حذف تاسك
     */
public function destroy(Request $request, $id)
{
    $user = $request->user();
    $task = Task::with('gantt.idea.owner')->findOrFail($id);
    $idea = $task->gantt->idea ?? null;
    $gantt = $task->gantt ?? null;
    if (!$idea || !$idea->owner || $idea->owner->id != $user->id) {
        return response()->json(['message' => 'لا يمكنك حذف هذه المهمة لأنها لا تخصك.'], 403);
    }

    if ($gantt && $gantt->approval_status === 'approved') {
        return response()->json(['message' => 'لا يمكن حذف المهمة بعد موافقة اللجنة.'], 403);
    }
    $task->delete();
    if ($gantt) {
        $this->updateGanttProgress($gantt);
    }
    return response()->json(['message' => 'تم حذف المهمة بنجاح']);
}
}

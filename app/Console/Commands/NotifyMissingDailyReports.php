<?php

namespace App\Console\Commands;

use App\Models\Placement;
use App\Models\Program;
use App\Models\User;
use App\Notifications\WorkspaceNotice;
use Illuminate\Console\Command;

class NotifyMissingDailyReports extends Command
{
    protected $signature = 'sims:notify-missing-daily-reports';

    protected $description = 'Notify students, supervisors and faculty about missing scheduled daily reports';

    public function handle(): int
    {
        $reportDate = today()->subDay();
        $placements = Placement::query()
            ->where('document_status', 'approved')
            ->where('status', 'active')
            ->whereDate('start_date', '<=', $reportDate)
            ->whereDate('end_date', '>=', $reportDate)
            ->whereHas('schedules', fn ($query) => $query->where('day_of_week', $reportDate->dayOfWeekIso))
            ->whereDoesntHave('dailyReports', fn ($query) => $query->whereDate('report_date', $reportDate))
            ->with(['student', 'fieldSupervisor', 'academicSupervisor'])
            ->get();

        $sent = 0;
        foreach ($placements as $placement) {
            $program = Program::query()->where('name', $placement->student->course)->with('coordinator')->first();
            $recipients = collect([
                $placement->student,
                $placement->fieldSupervisor,
                $placement->academicSupervisor,
                $program?->coordinator,
            ])->filter();
            $recipients = $recipients->merge(User::query()->where('role', 'admin')->get())->unique('id');

            foreach ($recipients as $recipient) {
                $alreadyNotified = $recipient->notifications()
                    ->where('type', WorkspaceNotice::class)
                    ->where('data->notice_type', 'missing_daily_report')
                    ->where('data->placement_id', $placement->id)
                    ->where('data->report_date', $reportDate->toDateString())
                    ->exists();

                if ($alreadyNotified) {
                    continue;
                }

                $recipient->notify(new WorkspaceNotice(
                    'Scheduled daily report not received',
                    'The daily activity report for '.$placement->student->name.' scheduled on '.$reportDate->format('M j, Y').' has not been submitted.',
                    [
                        'notice_type' => 'missing_daily_report',
                        'placement_id' => $placement->id,
                        'report_date' => $reportDate->toDateString(),
                    ],
                ));
                $sent++;
            }
        }

        $this->info('Sent '.$sent.' missing-report notification(s) for '.$reportDate->toDateString().'.');

        return self::SUCCESS;
    }
}

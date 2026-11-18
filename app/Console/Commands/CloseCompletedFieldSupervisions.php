<?php

namespace App\Console\Commands;

use App\Models\Placement;
use App\Models\User;
use App\Notifications\FieldSupervisorThankYouNotice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class CloseCompletedFieldSupervisions extends Command
{
    protected $signature = 'sims:close-completed-field-supervisions';

    protected $description = 'Thank field supervisors and close access to completed placements';

    public function handle(): int
    {
        $completed = Placement::query()
            ->with(['student', 'fieldSupervisor'])
            ->whereNotNull('field_supervisor_id')
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<', today())
            ->whereNull('field_supervisor_completed_at')
            ->get();

        foreach ($completed as $placement) {
            if ($placement->fieldSupervisor) {
                Notification::route('mail', $placement->fieldSupervisor->email)->notify(
                    new FieldSupervisorThankYouNotice($placement->student->name, $placement->company_name)
                );
            }

            $placement->forceFill(['field_supervisor_completed_at' => now()])->save();
        }

        User::query()
            ->where('role', 'field_supervisor')
            ->where('is_active', true)
            ->whereDoesntHave('fieldSupervisions', fn ($query) => $query->whereDate('end_date', '>=', today()))
            ->each(fn (User $supervisor) => $supervisor->forceFill(['is_active' => false])->save());

        $this->info('Closed '.$completed->count().' completed field-supervisor assignment(s).');

        return self::SUCCESS;
    }
}

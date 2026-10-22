<?php

namespace App\Filament\Resources\AdminApprovalRequests\Tables;

use App\Models\AdminApprovalRequest;
use App\Models\EscrowTransaction;
use App\Services\ChapaService;
use App\Services\LedgerService;
use App\States\Escrow\ForceRefunded;
use App\States\Escrow\ForceReleased;
use App\States\Job\Canceled as JobCanceled;
use App\States\Job\Completed;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminApprovalRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('action_type')
                    ->label('Action Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'FORCE_RELEASE' => 'info',
                        'FORCE_REFUND' => 'danger',
                        default => 'gray',
                    })
                    ->sortable()
                    ->searchable(),
                TextColumn::make('amount_santim')
                    ->label('Amount (ETB / Santim)')
                    ->formatStateUsing(fn ($state) => number_format($state / 100, 2).' ETB')
                    ->description(fn ($state) => number_format((float) $state, 0).' santim')
                    ->sortable(),
                TextColumn::make('requestedBy.full_name')
                    ->label('Requested By')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'PENDING' => 'warning',
                        'APPROVED' => 'success',
                        'REJECTED' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('justification_note')
                    ->label('Justification')
                    ->limit(45)
                    ->tooltip(fn (AdminApprovalRequest $record): ?string => $record->justification_note),
                TextColumn::make('approvedBy.full_name')
                    ->label('Checker')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('rejection_reason')
                    ->label('Rejection Reason')
                    ->limit(45)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('approved_at')
                    ->label('Decided At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Requested At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'PENDING' => 'Pending',
                        'APPROVED' => 'Approved',
                        'REJECTED' => 'Rejected',
                    ]),
                SelectFilter::make('action_type')
                    ->options([
                        'FORCE_RELEASE' => 'Force Release',
                        'FORCE_REFUND' => 'Force Refund',
                    ]),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon(Heroicon::Check)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Approve High-Value Maker-Checker Request')
                    ->modalDescription('Are you sure you want to approve this high-value request? Double-entry ledger entries and escrow transitions will be executed immediately.')
                    ->visible(fn (AdminApprovalRequest $record): bool => $record->status === 'PENDING')
                    ->disabled(fn (AdminApprovalRequest $record): bool => (Filament::auth()->id() ?? auth()->id()) === $record->requested_by_id)
                    ->tooltip(fn (AdminApprovalRequest $record): ?string => (Filament::auth()->id() ?? auth()->id()) === $record->requested_by_id ? 'Maker-Checker policy: The requesting administrator cannot approve their own submission.' : null)
                    ->action(function (AdminApprovalRequest $record): void {
                        $user = Filament::auth()->user() ?? auth()->user();
                        if (! $user) {
                            return;
                        }

                        // Strict Maker-Checker segregation (SRS §3 & AR-2)
                        if ($user->id === $record->requested_by_id) {
                            Notification::make()
                                ->title('Maker-Checker Violation')
                                ->body('Approver cannot be the same administrator who initiated the request.')
                                ->danger()
                                ->send();

                            return;
                        }

                        if ($record->status !== 'PENDING') {
                            Notification::make()
                                ->title('Invalid Action')
                                ->body('This request has already been '.strtolower($record->status).'.')
                                ->danger()
                                ->send();

                            return;
                        }

                        DB::transaction(function () use ($record, $user) {
                            if ($record->action_type === 'FORCE_RELEASE') {
                                if ($record->entity_type === EscrowTransaction::class || is_subclass_of($record->entity_type, EscrowTransaction::class)) {
                                    $escrow = EscrowTransaction::findOrFail($record->entity_id);
                                    if ($escrow->status->canTransitionTo(ForceReleased::class)) {
                                        $escrow->status->transitionTo(ForceReleased::class);
                                    }
                                    $escrow->update([
                                        'resolved_at' => now(),
                                        'resolved_by' => $user->id,
                                        'admin_resolution_note' => 'Maker-Checker approved force release: '.$record->justification_note,
                                    ]);

                                    app(LedgerService::class)->recordEscrowReleased($escrow);

                                    if ($escrow->job && $escrow->job->status->canTransitionTo(Completed::class)) {
                                        $escrow->job->status->transitionTo(Completed::class);
                                    }
                                }
                            } elseif ($record->action_type === 'FORCE_REFUND') {
                                if ($record->entity_type === EscrowTransaction::class || is_subclass_of($record->entity_type, EscrowTransaction::class)) {
                                    $escrow = EscrowTransaction::findOrFail($record->entity_id);
                                    if ($escrow->status->canTransitionTo(ForceRefunded::class)) {
                                        $escrow->status->transitionTo(ForceRefunded::class);
                                    }
                                    $escrow->update([
                                        'resolved_at' => now(),
                                        'resolved_by' => $user->id,
                                        'admin_resolution_note' => 'Maker-Checker approved force refund: '.$record->justification_note,
                                    ]);

                                    app(LedgerService::class)->recordRefund($escrow, 'Maker-Checker Approved Force Refund');

                                    if ($escrow->job && $escrow->job->status->canTransitionTo(JobCanceled::class)) {
                                        $escrow->job->status->transitionTo(JobCanceled::class);
                                    }

                                    try {
                                        app(ChapaService::class)->refund($escrow);
                                    } catch (\Throwable $e) {
                                        Log::warning('Chapa refund call failed during Maker-Checker approval: '.$e->getMessage(), [
                                            'escrow_id' => $escrow->id,
                                        ]);
                                    }
                                }
                            }

                            $record->update([
                                'status' => 'APPROVED',
                                'approved_by_id' => $user->id,
                                'approved_at' => now(),
                            ]);
                        });

                        Notification::make()
                            ->title('Maker-Checker Approved')
                            ->body('Request approved and ledger journals dispatched successfully.')
                            ->success()
                            ->send();
                    }),

                Action::make('reject')
                    ->label('Reject')
                    ->icon(Heroicon::XMark)
                    ->color('danger')
                    ->visible(fn (AdminApprovalRequest $record): bool => $record->status === 'PENDING')
                    ->disabled(fn (AdminApprovalRequest $record): bool => (Filament::auth()->id() ?? auth()->id()) === $record->requested_by_id)
                    ->tooltip(fn (AdminApprovalRequest $record): ?string => (Filament::auth()->id() ?? auth()->id()) === $record->requested_by_id ? 'Maker-Checker policy: The requesting administrator cannot reject their own submission.' : null)
                    ->schema([
                        Textarea::make('rejection_reason')
                            ->label('Rejection Reason')
                            ->required()
                            ->maxLength(1000)
                            ->placeholder('State specific reason why this high-value request is being rejected...'),
                    ])
                    ->action(function (AdminApprovalRequest $record, array $data): void {
                        $user = Filament::auth()->user() ?? auth()->user();
                        if (! $user) {
                            return;
                        }

                        // Strict Maker-Checker segregation (SRS §3 & AR-2)
                        if ($user->id === $record->requested_by_id) {
                            Notification::make()
                                ->title('Maker-Checker Violation')
                                ->body('Approver/Rejecter cannot be the same administrator who initiated the request.')
                                ->danger()
                                ->send();

                            return;
                        }

                        if ($record->status !== 'PENDING') {
                            Notification::make()
                                ->title('Invalid Action')
                                ->body('This request has already been '.strtolower($record->status).'.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $record->update([
                            'status' => 'REJECTED',
                            'approved_by_id' => $user->id,
                            'rejection_reason' => $data['rejection_reason'],
                            'approved_at' => now(),
                        ]);

                        Notification::make()
                            ->title('Maker-Checker Rejected')
                            ->body('Request rejected with reason provided.')
                            ->warning()
                            ->send();
                    }),

                EditAction::make(),
            ])
            ->toolbarActions([
                // Regulatory audit records are immutable and cannot be deleted via bulk action.
            ]);
    }
}

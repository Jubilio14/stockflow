<?php

namespace App\Services;

use App\Models\CashSession;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CashSessionService
{
    private const OPEN_GUARD =
        'GLOBAL_REGISTER';

    public function open(
        array $data,
        User $user
    ): CashSession {
        try {
            return DB::transaction(
                function () use ($data, $user) {
                    $existingSession =
                        CashSession::query()
                            ->with([
                                'cashier:id,name',
                            ])
                            ->open()
                            ->lockForUpdate()
                            ->first();

                    if ($existingSession) {
                        throw ValidationException::withMessages([
                            'session' => [
                                sprintf(
                                    'Kasir sedang digunakan oleh %s sejak %s.',
                                    $existingSession
                                        ->cashier
                                        ->name,

                                    $existingSession
                                        ->opened_at
                                        ->format(
                                            'd-m-Y H:i'
                                        )
                                ),
                            ],
                        ]);
                    }

                    $cashSession =
                        CashSession::create([
                            'session_number' => $this
                                ->generateSessionNumber(),

                            'cashier_id' => $user->id,

                            'opened_at' => now(),

                            'opening_cash' => $data['opening_cash'],

                            'cash_sales_total' => 0,

                            'status' => 'open',

                            'open_guard' => self::OPEN_GUARD,

                            'opening_notes' => $data[
                                    'opening_notes'
                                ] ?? null,
                        ]);

                    return $cashSession->load([
                        'cashier:id,name',
                    ]);
                }
            );
        } catch (QueryException $exception) {
            if (
                $this->isDuplicateKeyError(
                    $exception
                )
            ) {
                throw ValidationException::withMessages([
                    'session' => [
                        'Meja kasir sudah digunakan oleh sesi lain.',
                    ],
                ]);
            }

            throw $exception;
        }
    }

    public function close(
        CashSession $cashSession,
        array $data,
        User $user
    ): CashSession {
        return DB::transaction(
            function () use (
                $cashSession,
                $data,
                $user
            ) {
                $session =
                    CashSession::query()
                        ->with([
                            'cashier:id,name',
                        ])
                        ->lockForUpdate()
                        ->findOrFail(
                            $cashSession->id
                        );

                if (
                    $session->status !== 'open'
                ) {
                    throw ValidationException::withMessages([
                        'session' => [
                            'Sesi kasir sudah ditutup.',
                        ],
                    ]);
                }

                $isSessionOwner =
                    $session->cashier_id ===
                    $user->id;

                $isOwner =
                    $user->role === 'owner';

                if (
                    ! $isSessionOwner &&
                    ! $isOwner
                ) {
                    throw new AuthorizationException(
                        'Sesi hanya dapat ditutup oleh kasir yang membukanya atau owner.'
                    );
                }

                $expectedClosingCash = round(
                    (float) $session->opening_cash +
                    (float) $session->cash_sales_total,
                    2
                );

                $actualClosingCash = round(
                    (float) $data[
                        'actual_closing_cash'
                    ],
                    2
                );

                $difference = round(
                    $actualClosingCash -
                    $expectedClosingCash,
                    2
                );

                $session->update([
                    'closed_at' => now(),

                    'expected_closing_cash' => $expectedClosingCash,

                    'actual_closing_cash' => $actualClosingCash,

                    'difference' => $difference,

                    'status' => 'closed',

                    'open_guard' => null,

                    'closed_by' => $user->id,

                    'closing_notes' => $data[
                            'closing_notes'
                        ] ?? null,
                ]);

                return $session
                    ->fresh()
                    ->load([
                        'cashier:id,name',
                        'closer:id,name',
                    ]);
            }
        );
    }

    private function generateSessionNumber(): string
    {
        do {
            $sessionNumber = sprintf(
                'CS-%s-%s',
                now()->format('Ymd'),
                Str::upper(
                    Str::random(6)
                )
            );
        } while (
            CashSession::query()
                ->where(
                    'session_number',
                    $sessionNumber
                )
                ->exists()
        );

        return $sessionNumber;
    }

    private function isDuplicateKeyError(
        QueryException $exception
    ): bool {
        return
            $exception->getCode() === '23000'
            ||
            (
                $exception->errorInfo[1]
                ?? null
            ) === 1062;
    }
}

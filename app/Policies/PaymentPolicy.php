<?php

namespace App\Policies;

use App\Models\Job;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PaymentPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Users can view their own payment history
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Payment $payment): bool
    {
        // Users can only view payments they are involved in
        return $user->id === $payment->payer_id ||
               $user->id === $payment->payee_id ||
               $user->is_admin;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Only verified and active users can create payments
        return $user->is_active && $user->email_verified_at !== null;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Payment $payment): bool
    {
        // Payments cannot be directly updated for security reasons
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Payment $payment): bool
    {
        // Only admins can delete payments for audit purposes
        return $user->is_admin;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Payment $payment): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Payment $payment): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can initiate a payment for a job.
     */
    public function initiatePayment(User $user, Job $job): bool
    {
        // Only job owner can initiate payment, job must be completed
        return $user->id === $job->user_id &&
               $job->status === 'completed' &&
               $job->assigned_to !== null;
    }

    /**
     * Determine whether the user can release a payment.
     */
    public function release(User $user, Payment $payment): bool
    {
        // Only payer can release payment, and payment must be held
        return $user->id === $payment->payer_id &&
               $payment->status === 'held';
    }

    /**
     * Determine whether the user can request a refund.
     */
    public function requestRefund(User $user, Payment $payment): bool
    {
        // Only payer can request refund, payment must be held or released within dispute window
        $canRefund = $user->id === $payment->payer_id &&
                    in_array($payment->status, ['held', 'released']);

        // If payment is released, check if it's within dispute window (e.g., 7 days)
        if ($payment->status === 'released') {
            $canRefund = $canRefund && $payment->updated_at->diffInDays(now()) <= 7;
        }

        return $canRefund;
    }

    /**
     * Determine whether the user can process a refund.
     */
    public function processRefund(User $user, Payment $payment): bool
    {
        // Only admins can process refunds
        return $user->is_admin &&
               in_array($payment->status, ['held', 'released']);
    }

    /**
     * Determine whether the user can view payment details.
     */
    public function viewDetails(User $user, Payment $payment): bool
    {
        // Users involved in payment can view details, admins can view all
        return $user->id === $payment->payer_id ||
               $user->id === $payment->payee_id ||
               $user->is_admin;
    }

    /**
     * Determine whether the user can dispute a payment.
     */
    public function dispute(User $user, Payment $payment): bool
    {
        // Both payer and payee can dispute, payment must be held or released
        return ($user->id === $payment->payer_id || $user->id === $payment->payee_id) &&
               in_array($payment->status, ['held', 'released']) &&
               $payment->status !== 'disputed';
    }

    /**
     * Determine whether the user can resolve a dispute.
     */
    public function resolveDispute(User $user, Payment $payment): bool
    {
        // Only admins can resolve disputes
        return $user->is_admin && $payment->status === 'disputed';
    }

    /**
     * Determine whether the user can view transaction history.
     */
    public function viewHistory(User $user): bool
    {
        // All verified users can view their own transaction history
        return $user->is_active && $user->email_verified_at !== null;
    }

    /**
     * Determine whether the user can export payment data.
     */
    public function exportData(User $user, Payment $payment): bool
    {
        // Users can export their own payment data
        return $user->id === $payment->payer_id || $user->id === $payment->payee_id;
    }
}

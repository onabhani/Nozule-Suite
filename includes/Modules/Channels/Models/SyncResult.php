<?php

namespace Venezia\Modules\Channels\Models;

/**
 * Value object representing the result of a channel sync operation.
 *
 * Immutable after construction. Provides factory methods for common
 * outcomes (success, failure, partial) and serialises to an array
 * suitable for REST responses or log storage.
 */
class SyncResult {

    private bool $success;
    private string $message;
    private int $itemsSynced;
    private array $errors;

    public function __construct(
        bool $success,
        string $message,
        int $itemsSynced = 0,
        array $errors = []
    ) {
        $this->success     = $success;
        $this->message     = $message;
        $this->itemsSynced = $itemsSynced;
        $this->errors      = $errors;
    }

    /**
     * Create a successful sync result.
     */
    public static function success( string $message, int $itemsSynced = 0 ): static {
        return new static( true, $message, $itemsSynced );
    }

    /**
     * Create a failed sync result.
     */
    public static function failure( string $message, array $errors = [] ): static {
        return new static( false, $message, 0, $errors );
    }

    /**
     * Create a partial sync result (some items synced, some errors).
     */
    public static function partial( string $message, int $itemsSynced, array $errors = [] ): static {
        return new static( true, $message, $itemsSynced, $errors );
    }

    /**
     * Whether the sync was successful.
     */
    public function isSuccess(): bool {
        return $this->success;
    }

    /**
     * Get the human-readable result message.
     */
    public function getMessage(): string {
        return $this->message;
    }

    /**
     * Get the number of items that were synced.
     */
    public function getItemsSynced(): int {
        return $this->itemsSynced;
    }

    /**
     * Get any errors that occurred during sync.
     */
    public function getErrors(): array {
        return $this->errors;
    }

    /**
     * Check whether the result contains errors.
     */
    public function hasErrors(): bool {
        return ! empty( $this->errors );
    }

    /**
     * Convert to an associative array.
     */
    public function toArray(): array {
        return [
            'success'      => $this->success,
            'message'      => $this->message,
            'items_synced' => $this->itemsSynced,
            'errors'       => $this->errors,
        ];
    }
}

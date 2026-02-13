<?php

namespace Nozule\Modules\Channels\Validators;

use Nozule\Core\BaseValidator;
use Nozule\Modules\Channels\Repositories\ChannelMappingRepository;
use Nozule\Modules\Channels\Services\ChannelService;

/**
 * Validator for channel mapping data.
 */
class ChannelMappingValidator extends BaseValidator {

    private ChannelMappingRepository $repository;
    private ChannelService $channelService;

    public function __construct(
        ChannelMappingRepository $repository,
        ChannelService $channelService
    ) {
        $this->repository     = $repository;
        $this->channelService = $channelService;
    }

    /**
     * Validate data for creating a new channel mapping.
     */
    public function validateCreate( array $data ): bool {
        $rules = $this->getCreateRules();

        $valid = $this->validate( $data, $rules );

        // Check that the channel name is a registered connector.
        if ( ! empty( $data['channel_name'] ) ) {
            $registered = $this->channelService->getRegisteredConnectors();
            if ( ! isset( $registered[ $data['channel_name'] ] ) ) {
                $this->errors['channel_name'][] = sprintf(
                    __( 'Unknown channel "%s". Available channels: %s.', 'nozule' ),
                    $data['channel_name'],
                    implode( ', ', array_keys( $registered ) )
                );
                $valid = false;
            }
        }

        // Check for duplicate mapping (same channel + room type + external room).
        if ( $valid && ! empty( $data['channel_name'] ) && ! empty( $data['room_type_id'] ) ) {
            if ( $this->isDuplicateMapping( $data ) ) {
                $this->errors['channel_name'][] = __(
                    'A mapping for this channel and room type already exists.',
                    'nozule'
                );
                $valid = false;
            }
        }

        return $valid;
    }

    /**
     * Validate data for updating an existing channel mapping.
     *
     * @param int $mappingId The ID of the mapping being updated.
     */
    public function validateUpdate( array $data, int $mappingId ): bool {
        $rules = $this->getUpdateRules( $data );

        $valid = $this->validate( $data, $rules );

        // If channel_name is being changed, verify it is registered.
        if ( isset( $data['channel_name'] ) ) {
            $registered = $this->channelService->getRegisteredConnectors();
            if ( ! isset( $registered[ $data['channel_name'] ] ) ) {
                $this->errors['channel_name'][] = sprintf(
                    __( 'Unknown channel "%s". Available channels: %s.', 'nozule' ),
                    $data['channel_name'],
                    implode( ', ', array_keys( $registered ) )
                );
                $valid = false;
            }
        }

        // If the channel or room type is being changed, check for duplicates.
        if ( $valid && ( isset( $data['channel_name'] ) || isset( $data['room_type_id'] ) ) ) {
            $existing = $this->repository->find( $mappingId );
            if ( $existing ) {
                $checkData = [
                    'channel_name'   => $data['channel_name'] ?? $existing->channel_name,
                    'room_type_id'   => $data['room_type_id'] ?? $existing->room_type_id,
                    'external_room_id' => $data['external_room_id'] ?? $existing->external_room_id,
                ];

                if ( $this->isDuplicateMapping( $checkData, $mappingId ) ) {
                    $this->errors['channel_name'][] = __(
                        'A mapping for this channel and room type already exists.',
                        'nozule'
                    );
                    $valid = false;
                }
            }
        }

        // Validate status transitions.
        if ( isset( $data['status'] ) ) {
            $allowed = [ 'active', 'inactive', 'error' ];
            if ( ! in_array( $data['status'], $allowed, true ) ) {
                $this->errors['status'][] = sprintf(
                    __( 'Status must be one of: %s.', 'nozule' ),
                    implode( ', ', $allowed )
                );
                $valid = false;
            }
        }

        return $valid;
    }

    /**
     * Get the validation rules for creating a mapping.
     *
     * @return array<string, array>
     */
    private function getCreateRules(): array {
        return [
            'channel_name'    => [ 'required', 'max' => 50, 'slug' ],
            'room_type_id'    => [ 'required', 'integer' ],
            'external_room_id' => [ 'required', 'max' => 255 ],
        ];
    }

    /**
     * Get validation rules for partial updates (only validate provided fields).
     *
     * @return array<string, array>
     */
    private function getUpdateRules( array $data ): array {
        $rules     = [];
        $all_rules = [
            'channel_name'     => [ 'max' => 50, 'slug' ],
            'room_type_id'     => [ 'integer' ],
            'external_room_id' => [ 'max' => 255 ],
            'external_rate_id' => [ 'max' => 255 ],
            'status'           => [ 'in' => [ 'active', 'inactive', 'error' ] ],
        ];

        foreach ( $all_rules as $field => $fieldRules ) {
            if ( array_key_exists( $field, $data ) ) {
                $rules[ $field ] = $fieldRules;
            }
        }

        return $rules;
    }

    /**
     * Check whether a mapping with the same channel + room type already exists.
     *
     * @param array    $data      Must contain channel_name and room_type_id.
     * @param int|null $excludeId Mapping ID to exclude (for updates).
     */
    private function isDuplicateMapping( array $data, ?int $excludeId = null ): bool {
        $existing = $this->repository->getByChannel( $data['channel_name'] );

        foreach ( $existing as $mapping ) {
            if ( $excludeId && $mapping->id === $excludeId ) {
                continue;
            }

            if (
                (int) $mapping->room_type_id === (int) $data['room_type_id']
                && $mapping->external_room_id === ( $data['external_room_id'] ?? '' )
            ) {
                return true;
            }
        }

        return false;
    }
}

<?php

namespace Venezia\Modules\Pricing\Validators;

use Venezia\Core\BaseValidator;

class RatePlanValidator extends BaseValidator {

    public function validateCreate( array $data ): bool {
        return $this->validate( $data, [
            'name'          => [ 'required' ],
            'name_ar'       => [ 'required' ],
            'code'          => [ 'required', 'slug' ],
            'meal_plan'     => [ 'in' => 'room_only,breakfast,half_board,full_board,all_inclusive' ],
            'modifier_type' => [ 'in' => 'fixed,percentage' ],
        ] );
    }

    public function validateUpdate( array $data ): bool {
        return $this->validate( $data, [
            'name'          => [ 'min' => 1 ],
            'modifier_type' => [ 'in' => 'fixed,percentage' ],
        ] );
    }
}

<?php

namespace App\Enums;

// Replaces the ~17 stringly-typed 'functional_location'/'equipment' literals in v1.
enum CounterSubject: string
{
    case FunctionalLocation = 'functional_location';
    case Equipment = 'equipment';
}

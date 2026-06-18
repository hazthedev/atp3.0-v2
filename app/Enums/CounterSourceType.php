<?php

namespace App\Enums;

enum CounterSourceType: string
{
    case Manual = 'manual';
    case ManualPenalty = 'manual_penalty';
    case EventIngest = 'event_ingest';
    case PenaltyCascade = 'penalty_cascade';
    case Propagated = 'propagated';
    case CorrectionApproved = 'correction_approved';
    case Recalculation = 'recalculation';
}

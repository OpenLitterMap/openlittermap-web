<?php

namespace App\Enums;

enum Timescale: int
{
    case AllTime = 0;
    case Daily   = 1;
    case Weekly  = 2;
    case Monthly = 3;
    case Yearly  = 4;
}

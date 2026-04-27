<?php

namespace App\Enums\Auth;

enum CompanyRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';
}

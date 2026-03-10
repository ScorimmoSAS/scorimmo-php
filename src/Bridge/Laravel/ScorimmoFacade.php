<?php

namespace Scorimmo\Bridge\Laravel;

use Illuminate\Support\Facades\Facade;
use Scorimmo\Client\ScorimmoClient;

/**
 * @method static \Scorimmo\Client\LeadsResource leads()
 *
 * @see \Scorimmo\Client\ScorimmoClient
 */
class ScorimmoFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ScorimmoClient::class;
    }
}

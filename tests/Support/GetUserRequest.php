<?php

namespace Tests\Support;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetUserRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected int $userId
    ) {}

    public function resolveEndpoint(): string
    {
        return "/users/{$this->userId}";
    }
}
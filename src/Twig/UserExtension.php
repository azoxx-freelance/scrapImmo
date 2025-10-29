<?php

namespace App\Twig;

use App\Service\UserService;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

class UserExtension extends AbstractExtension implements GlobalsInterface
{
    private UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function getGlobals(): array
    {
        return [
            'current_user' => $this->userService->getCurrentUser()
        ];
    }
}
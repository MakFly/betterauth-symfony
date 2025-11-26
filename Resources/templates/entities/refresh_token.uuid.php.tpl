<?php

declare(strict_types=1);

namespace App\Entity;

use BetterAuth\Core\Entities\RefreshToken as BaseRefreshToken;
use Doctrine\ORM\Mapping as ORM;

/**
 * RefreshToken entity with UUID (string) user ID.
 */
#[ORM\Entity]
#[ORM\Table(name: 'refresh_tokens')]
class RefreshToken extends BaseRefreshToken
{
    #[ORM\Column(type: 'string', length: 36)]
    protected string $userId;

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function setUserId(string|int $userId): static
    {
        $this->userId = (string) $userId;

        return $this;
    }
}

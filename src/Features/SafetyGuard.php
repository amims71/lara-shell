<?php

namespace Amims71\LaraShell\Features;

use Illuminate\Contracts\Foundation\Application;

enum GuardLevel: string
{
    case Allow = 'allow';
    case Confirm = 'confirm';
    case Block = 'block';
}

class SafetyGuard
{
    /**
     * @param  array{environments?: string[], block?: string[], confirm?: string[]}  $config
     */
    public function __construct(
        private Application $app,
        private array $config
    ) {}

    /**
     * @param  string[]  $tokens  full argv incl. command name
     */
    public function classify(string $command, array $tokens = []): GuardLevel
    {
        $level = $this->rawLevel($command, $tokens);

        if ($level === GuardLevel::Allow) {
            return GuardLevel::Allow;
        }

        return $this->isGuardedEnvironment() ? $level : GuardLevel::Allow;
    }

    public function isGuardedEnvironment(): bool
    {
        $patterns = $this->config['environments'] ?? ['production'];

        return $this->app->environment(...$patterns);
    }

    private function rawLevel(string $command, array $tokens): GuardLevel
    {
        if (in_array($command, $this->config['block'] ?? [], true)) {
            return GuardLevel::Block;
        }

        if (in_array($command, $this->config['confirm'] ?? [], true)) {
            return GuardLevel::Confirm;
        }

        if (in_array('--force', $tokens, true) || in_array('-f', $tokens, true)) {
            return GuardLevel::Confirm;
        }

        return GuardLevel::Allow;
    }
}

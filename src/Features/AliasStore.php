<?php

namespace Amims71\LaraShell\Features;

class AliasStore
{
    /** @var array<string,string> */
    private array $aliases = [];

    /** @var array<string,string[]> */
    private array $macros = [];

    private bool $loaded = false;

    public function __construct(private string $configFile)
    {
    }

    /** @return array<string,string> alias name => expansion (first-word replacement) */
    public function aliases(): array
    {
        $this->ensureLoaded();

        return $this->aliases;
    }

    /** @return array<string,string[]> macro name (no @) => ordered step lines */
    public function macros(): array
    {
        $this->ensureLoaded();

        return $this->macros;
    }

    public function setAlias(string $name, string $expansion): void
    {
        $this->ensureLoaded();

        $this->aliases[$name] = $expansion;

        $this->persist();
    }

    public function removeAlias(string $name): void
    {
        $this->ensureLoaded();

        unset($this->aliases[$name]);

        $this->persist();
    }

    public function reload(): void
    {
        $this->loaded = false;

        $this->load();
    }

    private function ensureLoaded(): void
    {
        if ($this->loaded) {
            return;
        }

        $this->load();
    }

    private function load(): void
    {
        $this->aliases = [];
        $this->macros = [];

        if (is_file($this->configFile)) {
            $data = require $this->configFile;

            if (is_array($data)) {
                $aliases = $data['aliases'] ?? [];
                $macros = $data['macros'] ?? [];

                if (is_array($aliases)) {
                    foreach ($aliases as $name => $expansion) {
                        $this->aliases[(string) $name] = (string) $expansion;
                    }
                }

                if (is_array($macros)) {
                    foreach ($macros as $name => $steps) {
                        $this->macros[(string) $name] = array_values(array_map(
                            static fn ($step) => (string) $step,
                            is_array($steps) ? $steps : [$steps],
                        ));
                    }
                }
            }
        }

        $this->loaded = true;
    }

    private function persist(): void
    {
        $payload = [
            'aliases' => $this->aliases,
            'macros' => $this->macros,
        ];

        $contents = '<?php return '.var_export($payload, true).";\n";

        $dir = dirname($this->configFile);

        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        $tmp = $this->configFile.'.'.bin2hex(random_bytes(4)).'.tmp';

        file_put_contents($tmp, $contents, LOCK_EX);

        rename($tmp, $this->configFile);
    }
}

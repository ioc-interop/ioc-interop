<?php
declare(strict_types=1);

namespace IocInterop;

interface IocRegistry
{
    /**
     * Returns a shared instance of the specified class/interface.
     *
     * @param class-string $spec
     * @throws IocException when the shared instance cannot be returned.
     */
    public function get(string $spec) : object;

    /**
     * Can this registry return an instance of the specified class/interface?
     *
     * @param class-string $spec
     */
    public function has(string $spec) : bool;
}

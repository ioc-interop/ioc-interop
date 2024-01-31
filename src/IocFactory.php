<?php
declare(strict_types=1);

namespace IocInterop;

interface IocFactory
{
    /**
     * Returns a new instance of the specified class/interface.
     *
     * @param class-string $spec
     * @throws IocException when the new instance cannot be created.
     */
    public function new(string $spec) : object;
}

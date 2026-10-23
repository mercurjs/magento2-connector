<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

// Magento generates `<Class>Factory` at compile time; tests only need a shape to mock.
spl_autoload_register(static function (string $class): void {
    if (!(str_starts_with($class, 'Mercur\\') || str_starts_with($class, 'Magento\\')) || !str_ends_with($class, 'Factory')) {
        return;
    }
    $target = substr($class, 0, -7);
    if (!class_exists($target) && !interface_exists($target)) {
        return;
    }
    $namespace = substr($class, 0, (int) strrpos($class, '\\'));
    $short = substr($class, strrpos($class, '\\') + 1);
    eval(sprintf('namespace %s; class %s { public function create(array $data = []): \%s { throw new \LogicException("stub"); } }', $namespace, $short, $target));
});

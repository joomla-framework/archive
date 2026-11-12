<?php

/**
 * @copyright  Copyright (C) 2005 - 2026 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\Archive\Tests;

use Joomla\Archive\Bzip2;
use Joomla\Archive\ExtractableInterface;
use Joomla\Archive\Gzip;
use Joomla\Archive\Tar;
use Joomla\Archive\Zip;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * Test class for the signatures of Joomla\Archive\ExtractableInterface.
 */
class ExtractableInterfaceTest extends TestCase
{
    /**
     * @return  array<string, array{class-string}>
     */
    public static function dataClasses(): array
    {
        return [
            'interface' => [ExtractableInterface::class],
            'Bzip2'     => [Bzip2::class],
            'Gzip'      => [Gzip::class],
            'Tar'       => [Tar::class],
            'Zip'       => [Zip::class],
        ];
    }

    #[DataProvider('dataClasses')]
    #[TestDox('extract() and isSupported() have native types')]
    public function testNativeTypes(string $class)
    {
        $extract = new \ReflectionMethod($class, 'extract');

        $this->assertSame(
            ['string', 'string'],
            array_map(static fn (\ReflectionParameter $parameter) => (string) $parameter->getType(), $extract->getParameters())
        );
        $this->assertSame('bool', (string) $extract->getReturnType());
        $this->assertSame('bool', (string) (new \ReflectionMethod($class, 'isSupported'))->getReturnType());
    }
}

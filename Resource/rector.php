<?php

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    throw new \LogicException();
}

/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) EC-CUBE CO.,LTD. All Rights Reserved.
 *
 * https://www.ec-cube.co.jp/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Rector\Config\RectorConfig;
use Rector\Renaming\Rector\Name\RenameClassRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Symfony\Set\SymfonySetList;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    // EC-CUBE 4.4 の最小サポートバージョンに合わせる
    ->withPhpVersion(PhpVersion::PHP_82)
    // プラグインのソースディレクトリ
    ->withPaths([
        dirname(__DIR__).'/Controller',
        dirname(__DIR__).'/Form',
        dirname(__DIR__).'/Service',
        dirname(__DIR__).'/PluginManager.php',
        dirname(__DIR__).'/SalesReportNav.php',
    ])
    ->withSkip([
        dirname(__DIR__).'/vendor',
        dirname(__DIR__).'/node_modules',
    ])
    ->withSets([
        LevelSetList::UP_TO_PHP_82,
        // Symfony 7.4 対応 (@Route → #[Route], buildForm(): void 等)
        SymfonySetList::SYMFONY_74,
        SymfonySetList::SYMFONY_CODE_QUALITY,
    ])
    // Symfony 等のアノテーション → アトリビュート変換を有効化
    ->withAttributesSets()
    // #[Route] は付与されるが use 文が旧 Annotation のまま残るため Attribute へ統一する
    ->withConfiguredRule(RenameClassRector::class, [
        'Symfony\Component\Routing\Annotation\Route' => 'Symfony\Component\Routing\Attribute\Route',
    ])
    ->withImportNames(
        importShortClasses: false,
        importDocBlockNames: true,
        importNames: true
    )
    ->withParallel();

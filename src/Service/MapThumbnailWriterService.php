<?php declare(strict_types=1);

/*
 * (c) Vladimir "allejo" Jimenez <me@allejo.io>
 *
 * For the full copyright and license information, please view the
 * LICENSE.md file that was distributed with this source code.
 */

namespace App\Service;

use allejo\bzflag\graphics\SVG\Radar\WorldRenderer;
use allejo\bzflag\replays\ReplayHeader;
use App\Entity\MapThumbnail;
use App\Entity\Replay;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Filesystem;

class MapThumbnailWriterService implements IThumbnailWriter
{
    use ThumbnailWriterTrait;

    public const FOLDER_NAME = 'map-thumbnails';

    /** @var EntityManagerInterface */
    private $em;

    /** @var Filesystem */
    private $fs;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->em = $entityManager;
        $this->fs = new Filesystem();
    }

    public function writeThumbnail(ReplayHeader $replayHeader, Replay $replay, bool $regenerate): bool
    {
        /** @var MapThumbnail $existingThumbnail */
        $existingThumbnail = $this->em->getRepository(MapThumbnail::class)->findOneBy([
            'worldHash' => $replayHeader->getWorldDatabase()->getWorldHash(),
        ]);

        if ($existingThumbnail !== null) {
            $replay->setMapThumbnail($existingThumbnail);

            if ($regenerate) {
                $this->generateThumbnail($replayHeader);
            }

            return true;
        }

        $svgFilename = $this->generateThumbnail($replayHeader);

        $thumbnail = new MapThumbnail();
        $thumbnail->setWorldHash($replayHeader->getWorldDatabase()->getWorldHash());
        $thumbnail->setFilename($svgFilename);

        $replay->setMapThumbnail($thumbnail);

        $this->em->persist($thumbnail);

        return true;
    }

    private function generateThumbnail(ReplayHeader $header): string
    {
        $render = new WorldRenderer($header->getWorldDatabase());
        $svgOutput = $render->exportStringSVG();
        $svgFilename = $header->getWorldDatabase()->getWorldHash() . '.svg';

        $this->writeFile($svgFilename, $svgOutput);

        return $svgFilename;
    }

    private function writeFile(string $filename, string $content): void
    {
        $this->fs->dumpFile($this->getFilePath($filename), $content);
    }

    private function getFilePath(string $filename): string
    {
        return sprintf('%s/%s/%s', $this->targetDirectory, self::FOLDER_NAME, $filename);
    }
}

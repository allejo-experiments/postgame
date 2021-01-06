<?php declare(strict_types=1);

require 'vendor/autoload.php';

use allejo\bzflag\replays\Replay;

use SVG\SVG;
use SVG\Nodes\Shapes\SVGRect;

class GameMovement
{
    /**
     * Size of the map in the replay file
     */
    private $grid_size;
    /**
     * Size of the heatmap
     */
    private $heatmap_size;
    /**
     * Hashmap with {userID: Callsign} since userID is temporary
     */
    private $id_to_callsign;
    /**
     * Hashmap with {Callsign: Heatmap}
     */
    private $callsign_heatmap;


    /**
     * GameMovement constructor.
     * @param int $grid_size
     * @param int $heatmap_size
     */
    public function __construct(int $grid_size, int $heatmap_size)
    {

        $this->grid_size = $grid_size;
        $this->heatmap_size = $heatmap_size;
    }

    /**
     * @return int
     */
    public function getGridSize(): int
    {
        return $this->grid_size;
    }

    /**
     * @return int
     */
    public function getHeatmapSize(): int
    {
        return $this->heatmap_size;
    }

    /**
     * @return array
     */
    public function getCallsignHeatmap(): array
    {
        return $this->callsign_heatmap;
    }

    /**
     * Add A position from the replay file to the heatmap
     * @param float $x
     * @param float $y
     * @param string $callsign
     */
    public function addPosition(float $x, float $y, string $callsign): void
    {
        //Shift to N
        $positive_x = $x + ($this->grid_size / 2);
        $positive_y = $y + ($this->grid_size / 2);

        $grid_quadrant_size = $this->grid_size / $this->heatmap_size;

        $grid_x = $this->heatmap_size - 1 - floor($positive_y / $grid_quadrant_size);
        $grid_y = floor($positive_x / $grid_quadrant_size);

        ($this->callsign_heatmap[$callsign])[$grid_x][$grid_y]++;
    }

    /**
     * Import and Process a replay file
     * @param string $location
     * @throws \allejo\bzflag\networking\Packets\PacketInvalidException
     * @throws \allejo\bzflag\replays\Exceptions\InvalidReplayException
     * @throws \allejo\bzflag\world\Exceptions\InvalidWorldCompressionException
     * @throws \allejo\bzflag\world\Exceptions\InvalidWorldDatabaseException
     */
    public function replayHeatmap(string $location): void
    {
        $replay = new Replay($location);
        $this->id_to_callsign = array();
        $this->callsign_heatmap = array();

        foreach ($replay->getPacketsIterable() as $packet) {
            if ($packet->getPacketType() === "MsgAddPlayer") {

                if (!isset( $this->callsign_heatmap[$packet->getCallsign()])) {
                    $this->callsign_heatmap[$packet->getCallsign()] = array_fill(0, $this->heatmap_size, array_fill(0, $this->heatmap_size, 0));
                }

                $this->id_to_callsign[$packet->getPlayerIndex()] = $packet->getCallsign();
            }


            if ($packet->getPacketType() === "MsgPlayerUpdate") {
                $callsign = $this->id_to_callsign[$packet->getPlayerId()];
                $position = $packet->getState()->position;
                $this->addPosition($position[0], $position[1], $callsign);

            }
            if ($packet->getPacketType() === "MsgRemovePlayer") {
                unset($this->id_to_callsign[$packet->getPlayerId()]);
            }
        }
    }
}

function maxval(array $x): int
{
    $max = 0;
    foreach ($x as $row){
        $max_col = max($row);
        if ($max_col>$max){
            $max = $max_col;
        }
    }
    return $max;
}

function gradient($t,$start, $middle, $end) {
    return $t>=0.5 ? linear($middle,$end,($t-.5)*2) : linear($start,$middle,$t*2);
}

function linear( $start, $end, $x) {
    $r = byteLinear($start[1].$start[2], $end[1].$end[2], $x);
    $g = byteLinear($start[3].$start[4], $end[3].$end[4], $x);
    $b = byteLinear($start[5].$start[6], $end[5].$end[6], $x);
    return "#".$r.$g.$b;
}

function byteLinear($a,$b,$x) {
    $y = (hexdec(('0x'.$a))*(1-$x) + hexdec(('0x'.$b))*$x)|0;
    return dechex($y);
}


$movement = new GameMovement(300, 10);
$movement->replayHeatmap('replay_ID_change.rec');
$heatmap_list = $movement->getCallsignHeatmap();

$svg_list = [];

$newRange = 255;

foreach ($heatmap_list as $heatmap){
    $oldRange = maxval($heatmap);
    $image = new SVG(400, 400);
    $doc = $image->getDocument();
    for ($i = 0; $i < count($heatmap); $i++) {
        for ($j = 0; $j < count($heatmap[$i]); $j++) {
            $square = new SVGRect(40*$j, 40*$i, 40, 40);
            $colour = gradient($heatmap[$i][$j]/$oldRange, "#1a2a6c", "#b21f1f", "#fdbb2d");

            $square->setStyle('fill', $colour);
            $doc->addChild($square);
        }
    }
    array_push($svg_list, $image);
}

echo $svg_list[4];

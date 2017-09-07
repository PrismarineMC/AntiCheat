<?php

/**
 * @name AntiCheat
 * @version 1.0.0
 * @author Encritary
 * @api 3.0.0
 * @main AntiCheat\AntiCheat
 */

namespace AntiCheat;

use pocketmine\entity\Effect;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\math\Vector3;
use pocketmine\plugin\PluginBase;

class AntiCheat extends PluginBase implements Listener{

	protected $ticking = [];
	protected $unhandlingBlocks = []; //Blocks that must not be handled by anti-cheat

	public function onEnable(){
		if($this->getServer()->getName() !== "Prismarine"){
			$this->getLogger()->warning("This server software isn't supported. Change it to github.com/PrismarineMC/Prismarine or enjoy featureless AntiCheat.");
			return;
		}
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->unhandlingBlocks = [85, 113, 183, 184, 185, 186, 187, 212, 139];
	}

	public function onPlayerMove(PlayerMoveEvent $event){
		$player = $event->getPlayer();
		$currentTick = round(microtime(true) * 20);
		if(!isset($this->ticking[spl_object_hash($player)])){
			$this->ticking[spl_object_hash($player)] = $currentTick;
		}
		$tickDiff = $currentTick - $this->ticking[spl_object_hash($player)];
		if($tickDiff == 0)
			$tickDiff = 1;
		$this->ticking[spl_object_hash($player)] = $currentTick;
		$newPos = $event->getTo();
		$diffX = $player->x - $newPos->x;
		$diffY = $player->y - $newPos->y;
		$diffZ = $player->z - $newPos->z;
		$diff = ($diffX ** 2 + $diffY ** 2 + $diffZ ** 2) / ($tickDiff ** 2);
		if($diff > 0.0625){
			$player->sendMessage("§l§4[AntiCheat] §r§cDon't use cheats, otherwise you will be banned by admin!");
 			$event->setCancelled();
 			return;
 		}
 		$speed = $newPos->subtract($player->getLocation())->divide($tickDiff);
 		if($player->isAlive() and !$player->isSpectator()){
			if($player->getInAirTicks() > 10 and !$player->isSleeping() and !$player->isImmobile() and !$player->getAllowFlight()){
				$blockUnder = $player->getLevel()->getBlock(new Vector3($player->x, $player->y - 1, $player->z));
				if(in_array($blockUnder->getId(), $this->unhandlingBlocks)){ //Fences are handling incorrectly by PMMP
					$player->resetAirTicks();
					return;
				}
				$expectedVelocity = -0.08 / 0.02 - (-0.08 / 0.02) * exp(-0.02 * ($player->getInAirTicks() - $player->getStartAirTicks()));
				$jumpVelocity = (0.42 + ($player->hasEffect(Effect::JUMP) ? ($player->getEffect(Effect::JUMP)->getEffectLevel() /10) : 0)) / 0.42;
				$diff = (($speed->y - $expectedVelocity) ** 2) / $jumpVelocity;
				if($diff > 0.6 and $expectedVelocity < $speed->y){
					if($player->getInAirTicks() < 100){
						$player->setMotion(new Vector3(0, $expectedVelocity, 0));
					}else{
						$player->sendMessage("§l§4[AntiCheat] §r§cDon't use cheats, otherwise you will be banned by admin!");
					}
				}
			}
		}
	}

	public function onPlayerQuit(PlayerQuitEvent $event){
		if(isset($this->ticking[spl_object_hash($event->getPlayer())])){
			unset($this->ticking[spl_object_hash($event->getPlayer())]);
		}
	}

}

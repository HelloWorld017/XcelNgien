<?php

namespace org\Khinenw\xcel;

use org\Khinenw\xcel\event\GameStatusUpdateEvent;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

abstract class XcelRoundGame extends XcelGame{
	protected $round;

	const STATUS_ROUND_PREPARATION = 0;
	const STATUS_IN_ROUND = 1;

	protected $roundStatus = self::STATUS_ROUND_PREPARATION;

	public static $defaultConfigs = [
		"need-players" => 2,
		"preparation-term" => 300,
		"round-term" => 1200,
		"max-round" => 5,
		"preparation-round-term" => 150
	];

	public function resetGame(){
		parent::resetGame();
		$this->round = 0;
	}

	public function startGame(){
		parent::startGame();
		$this->round = self::STATUS_IN_ROUND;
	}

	public function onTick(){
		$this->innerTick++;
		$this->roundTick++;

		switch($this->currentStatus){
			case self::STATUS_NOT_STARTED:
				break;

			case self::STATUS_PREPARING:
				if($this->roundTick >= $this->configs["preparation-term"]){
					$this->startGame();
				}

				foreach($this->players as $xcelPlayer){
					$xcelPlayer->getPlayer()->teleport($this->getPreparationPosition($xcelPlayer));
				}

				break;

			case self::STATUS_IN_GAME:
				if($this->roundStatus === self::STATUS_IN_ROUND){
					if($this->roundTick >= $this->configs["round-term"]){
						$this->roundTick = 0;
						$this->roundStatus = self::STATUS_ROUND_PREPARATION;
						$this->broadcastMessageForPlayers(TextFormat::AQUA . XcelNgien::getTranslation("ROUND_FINISHED", $this->round));
						Server::getInstance()->getPluginManager()->callEvent(new GameStatusUpdateEvent($this));
						$this->prepareNextRound();
					}
				}else{
					if($this->roundTick >= $this->configs["preparation-round-term"]){
						$this->roundTick = 0;
						$this->round++;

						if($this->round >= $this->configs["max-round"]){
							$alive = [];
							foreach($this->players as $xcelPlayer){
								if($xcelPlayer->isAlive()) $alive[] = $xcelPlayer;
							}

							$this->winGame($alive);
							return;
						}
						Server::getInstance()->getPluginManager()->callEvent(new GameStatusUpdateEvent($this));

						$this->broadcastMessageForPlayers(TextFormat::AQUA . XcelNgien::getTranslation("ROUND_STARTED", $this->round));
					}
				}


				if($this->roundTick % self::MESSAGE_SEND_TERM === 0){
					foreach($this->players as $xcelPlayer){
						$xcelPlayer->getPlayer()->sendTip($this->getTip($xcelPlayer));
					}
				}
		}

		$this->afterTick();
	}

	public abstract function prepareNextRound();

	public function getRound(){
		return $this->round;
	}
}

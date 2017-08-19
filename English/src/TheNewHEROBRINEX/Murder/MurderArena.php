<?php
namespace TheNewHEROBRINE\Murder;
use pocketmine\item\Item;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\utils\TextFormat;
use TheNewHEROBRINE\Murder\entities\MurderPlayer;
class MurderArena {
    const GAME_IDLE = 0;
    const GAME_STARTING = 1;
    const GAME_RUNNING = 2;
    /** @var MurderMain $plugin */
    private $plugin;
    /** @var string $name */
    private $name;
    /** @var int $countdown */
    private $countdown;
 // /** @var int $maxTime */
 // private $maxTime;
    /** @var int $status */
    private $state = self::GAME_IDLE;
    /** @var MurderPlayer[] $players */
    private $players = [];
    /** @var array $skins */
    private $skins = [];
    /** @var MurderPlayer $murderer */
    private $murderer;
    /** @var MurderPlayer[] $bystanders */
    private $bystanders;
    /** @var array $spawns */
    private $spawns;
    /** @var array $espawns */
    private $espawns;
    /** @var Level $world */
    private $world;
    /** @var int $spawnEmerald */
    private $spawnEmerald = 10;
    /**
     * @param MurderMain $plugin
     * @param string $name
     * @param array $spawns
     * @param array $espawns
     */
    public function __construct(MurderMain $plugin, string $name, array $spawns, array $espawns) {
        $this->spawns = $spawns;
        $this->espawns = $espawns;
        $this->plugin = $plugin;
        $this->name = $name;
        $this->world = $this->getPlugin()->getServer()->getLevelByName($name);
        $this->countdown = $this->getPlugin()->getCountdown();
    }
    public function tick() {
        if ($this->isStarting()){
            if ($this->countdown == 0){
                $this->start();
                $this->broadcastMessage("The game is starting!");
            }
            $this->broadcastPopup(TextFormat::YELLOW . "Inizio tra " . TextFormat::WHITE . $this->countdown . TextFormat::YELLOW . "s");
            $this->countdown--;
        }
        elseif ($this->isRunning()){
            $padding = str_repeat(" ", 55);
            foreach ($this->getPlayers() as $player){
                $player->sendPopup(
                    $padding . MurderMain::MESSAGE_PREFIX . "\n" .
                    $padding . TextFormat::AQUA . "Role: " . TextFormat::GREEN . $this->getRole($player) . "\n" .
                    $padding . TextFormat::AQUA . "Emeralds: " . TextFormat::YELLOW . $player->getItemCount() . "/5\n" .
                    $padding . TextFormat::AQUA . "Name: " . "\n$padding" . TextFormat::GREEN . $player->getDisplayName() . str_repeat("\n", 3));
            }
            if ($this->spawnEmerald == 0){
                $this->spawnEmerald($this->espawns[array_rand($this->espawns)]);
                $this->spawnEmerald = 10;
            }
            $this->spawnEmerald--;
        }
    }
    /**
     * @param Player $player
     */
    public function join(Player $player) {
        if (!$this->isRunning()){
            if (!$this->getPlugin()->getArenaByPlayer($player)){
                if (count($this->getPlayers()) < count($this->spawns)){
                    $this->players[] = $player;
                    $player->getInventory()->clearAll();
                    $player->getInventory()->sendContents($player);
                    $player->teleport($this->getPlugin()->getHub()->getSpawnLocation());
                    $this->broadcastMessage(str_replace("{player}", $player->getName(), $this->getPlugin()->getJoinMessage()));
                    if (count($this->getPlayers()) >= 2 && $this->isIdle()){
                        $this->state = self::GAME_STARTING;
                    }
                }else{
                    $player->sendMessage(TextFormat::RED . "That arena is full!");
                }
            }else{
                $player->sendMessage(TextFormat::RED . "You are already in a game!");
            }
        } else{
            $player->sendMessage(TextFormat::RED . "The game has already started!");
        }
    }
    /**
     * @param Player $player
     * @param bool $silent
     */
    public function quit(Player $player, $silent = false) {
        if ($this->inArena($player)){
            if (!$silent){
                $this->broadcastMessage(str_replace("{player}", $player->getName() !== $player->getDisplayName() ? $player->getDisplayName() . " (" . $player->getName() . ") " : $player->getName(), $this->getPlugin()->getQuitMessage()));
            }
            $this->closePlayer($player);
            if ($this->isRunning()){
                if ($this->isMurderer($player)){
                    $this->stop("The innocent have won the match: " . $this);
                }
                elseif (count($this->getPlayers()) === 1){
                    $this->stop("The murderer won the match: " . $this);
                }
            }
            elseif ($this->isStarting()){
                if (count($this->getPlayers()) < 2){
                    $this->state = self::GAME_IDLE;
                }
            }
        }
    }
    public function start() {
        $this->state = self::GAME_RUNNING;
        $skins = [];
        foreach ($this->getPlayers() as $player) {
            $skins[$player->getName()] = $player->getSkinData();
        }
        $this->skins = $skins;
        if (count(array_unique($this->getSkins())) > 1){
            do {
                shuffle($skins);
            } while (array_values($this->getSkins()) == $skins);
        }
        $players = $this->getPlayers();
        do {
            shuffle($players);
        } while ($this->getPlayers() == $players);
        foreach ($this->getPlayers() as $player) {
            $player->setSkin(array_shift($skins), $player->getSkinId());
            $name = array_shift($players)->getName();
            $player->setDisplayName($name);
            $player->setNameTag($name);
            $player->setNameTagAlwaysVisible(false);
        }
        $random = array_rand($this->getPlayers(), 2);
        shuffle($random);
        $this->murderer = $this->getPlayers()[$random[0]];
        $this->bystanders[] = $this->getPlayers()[$random[1]];
        $this->getMurderer()->getInventory()->clearAll();
        $this->getMurderer()->getInventory()->setHeldItemIndex(0);
        $this->getMurderer()->getInventory()->resetHotbar(true);
        $this->getMurderer()->getInventory()->setItemInHand(Item::get(Item::WOODEN_SWORD)->setCustomName("Knife"));
        $this->getMurderer()->setButtonText("Lancia");
        $this->getMurderer()->setFood($this->murderer->getMaxFood());
        $this->getMurderer()->addTitle(TextFormat::RED . "Murderer", TextFormat::RED . "You are the murderer, KILL EVERYONE!");
        $this->getBystanders()[0]->getInventory()->clearAll();
        $this->getBystanders()[0]->getInventory()->setHeldItemIndex(0);
        $this->getBystanders()[0]->getInventory()->resetHotbar(true);
        $this->getBystanders()[0]->getInventory()->setItemInHand(Item::get(Item::FISHING_ROD)->setCustomName("Gun"));
        $this->getBystanders()[0]->setButtonText("Shoot");
        $this->getBystanders()[0]->setFood(6);
        $this->getBystanders()[0]->addTitle(TextFormat::AQUA . "Bystander", TextFormat::AQUA . "With a secret weapon!");
        $spawns = $this->spawns;
        shuffle($spawns);
        foreach ($this->getPlayers() as $player) {
            $player->setGamemode($player::ADVENTURE);
            if ($player !== $this->getMurderer() && $player != $this->getBystanders()[0]){
                $player->setButtonText("Shoot");
                $player->setFood(6);
                $player->addTitle(TextFormat::AQUA . "Bystander", TextFormat::AQUA . "Kill the murderer!");
                $this->bystanders[] = $player;
            }
            $spawn = array_shift($spawns);
            $player->teleport(new Position($spawn[0], $spawn[1], $spawn[2], $this->getWorld()));
        }
        foreach ($this->espawns as $espawn) {
            $this->spawnEmerald($espawn);
        }
    }
    /**
     * @param string $message
     */
    public function stop(string $message) {
        if ($this->isRunning()){
            foreach ($this->getWorld()->getPlayers() as $player) {
                if ($this->inArena($player)){
                    $this->closePlayer($player);
                }
                else{
                    $player->teleport($this->getPlugin()->getServer()->getDefaultLevel()->getSpawnLocation());
                }
            }
            $this->getPlugin()->broadcastMessage($message);
            $this->players = [];
            $this->skins = [];
            $this->countdown = $this->getPlugin()->getCountdown();
            $this->bystanders = [];
            $this->murderer = null;
            $this->spawnEmerald = 10;
            $this->state = self::GAME_IDLE;
            foreach ($this->getWorld()->getEntities() as $entity) {
                $entity->close();
            }
        }
    }
    /**
     * @param Player $player
     */
    public function closePlayer(Player $player){
        /** @var MurderPlayer $player */
        if ($this->inArena($player)){
            $player->setNameTagAlwaysVisible(true);
            $player->setNameTag($player->getName());
            $player->setDisplayName($player->getName());
            if (isset($this->getSkins()[$player->getName()])){
                $player->setSkin($this->getSkins()[$player->getName()], $player->getSkinId());
            }
            $player->getInventory()->clearAll();
            $player->getInventory()->sendContents($player);
            $player->setButtonText("");
            $player->setGamemode($this->getPlugin()->getServer()->getDefaultGamemode());
            $player->setHealth($player->getMaxHealth());
            $player->setFood($player->getMaxFood());
            unset($this->players[array_search($player, $this->getPlayers())]);
            $player->teleport($this->getPlugin()->getServer()->getDefaultLevel()->getSpawnLocation());
        }
    }
    /**
     * @param array $espawn
     */
    public function spawnEmerald(array $espawn) {
        $this->getWorld()->dropItem(new Vector3($espawn[0], $espawn[1], $espawn[2]), Item::get(Item::EMERALD));
    }
    /**
     * @param Player $player
     * @return bool
     */
    public function inArena(Player $player): bool {
        return in_array($player, $this->getPlayers());
    }
    /**
     * @param Player $player
     * @return string|null
     */
    public function getRole(Player $player): string {
        if ($this->inArena($player)){
            return $this->isMurderer($player) ? "Murderer" : "Bystander";
        }
        return null;
    }
    /**
     * @param string $msg
     */
    public function broadcastMessage(string $msg) {
        $this->getPlugin()->broadcastMessage($msg, $this->getPlayers());
    }
    /**
     * @param string $msg
     */
    public function broadcastPopup(string $msg) {
        $this->getPlugin()->broadcastPopup($msg, $this->getPlayers());
    }
    /**
     * @param Player $player
     * @return bool
     */
    public function isMurderer(Player $player): bool {
        return $this->getMurderer() === $player;
    }
    /**
     * @param Player $player
     * @return bool
     */
    public function isBystander(Player $player): bool {
        return in_array($player, $this->getBystanders());
    }
    /**
     * @return int
     */
    public function isIdle(): int {
        return $this->state == self::GAME_IDLE;
    }
    /**
     * @return int
     */
    public function isStarting(): int {
        return $this->state == self::GAME_STARTING;
    }
    /**
     * @return bool
     */
    public function isRunning(): bool {
        return $this->state == self::GAME_RUNNING;
    }
    /**
     * @return MurderPlayer
     */
    public function getMurderer(): MurderPlayer {
        return $this->murderer;
    }
    /**
     * @return MurderPlayer[]
     */
    public function getBystanders(): array {
        return $this->bystanders;
    }
    /**
     * @return MurderPlayer[]
     */
    public function getPlayers(): array {
        return $this->players;
    }
    /**
     * @return Level
     */
    public function getWorld(): Level {
        return $this->world;
    }
    /**
     * @return array
     */
    public function getSkins(): array {
        return $this->skins;
    }
    /**
     * @return MurderMain
     */
    public function getPlugin(): MurderMain {
        return $this->plugin;
    }
    /**
     * @return string
     */
    public function getName(): string {
        return $this->name;
    }
    /**
     * @return string
     */
    public function __toString(): string {
        return $this->getName();
    }
}

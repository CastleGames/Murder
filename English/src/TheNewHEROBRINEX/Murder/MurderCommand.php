<?php
namespace TheNewHEROBRINE\Murder;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginIdentifiableCommand;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\event\TranslationContainer;
use pocketmine\Player;
use pocketmine\plugin\Plugin;
use pocketmine\utils\TextFormat;
class MurderCommand extends Command implements PluginIdentifiableCommand {
    /** @var MurderMain $plugin */
    private $plugin;
    /**
     * @param MurderMain $plugin
     */
    public function __construct(MurderMain $plugin) {
        parent::__construct("murder", "The main Murder plugin command", "/murder join {arena}|quit|setarena {players} {emeralds}", ["mdr"]);
        $this->setPermission("murder.command.join;murder.command.quit;murder.command.setarena");
        $this->plugin = $plugin;
    }
    /**
     * @param CommandSender $sender
     * @param string $commandLabel
     * @param array $args
     * @return bool
     */
    public function execute(CommandSender $sender, string $commandLabel, array $args) {
        if (!$this->testPermission($sender)){
            return true;
        }
        if (!$sender instanceof Player){
            $sender->sendMessage(TextFormat::RED . "You can only run this command in-game!");
            return true;
        }
        if (count($args) === 0 or count($args) > 3){
            throw new InvalidCommandSyntaxException();
        }
        switch (array_shift($args)) {
            case "join":
                if ($this->badPerm($sender, "join")){
                    return false;
                }
                if (count($args) !== 1){
                    throw new InvalidCommandSyntaxException();
                }
                if ($arena = $this->getPlugin()->getArenaByName($args[0])){
                    if (!$this->getPlugin()->getServer()->isLevelLoaded($arena)){
                        $this->getPlugin()->getServer()->loadLevel($arena);
                    }
                    $arena->join($sender);
                }
                else{
                    $sender->sendMessage(TextFormat::RED . "The arena named $args[0] does not exist!");
                }
                return true;
            case "quit":
                if ($this->badPerm($sender, "quit")){
                    return false;
                }
                if (count($args)){
                    throw new InvalidCommandSyntaxException();
                }
                if ($arena = $this->getPlugin()->getArenaByPlayer($sender)){
                    $arena->quit($sender);
                }
                else{
                    $sender->sendMessage(TextFormat::RED . "You are not in any Murder game!");
                }
                return true;
            case "setarena":
                if ($this->badPerm($sender, "setarena")){
                    return false;
                }
                if (count($args) < 2 or !ctype_digit(implode("", $args))){
                    throw new InvalidCommandSyntaxException();
                }
                $world = $sender->getLevel()->getFolderName();
                $name = $sender->getName();
                $this->getPlugin()->getListener()->setspawns[$name][$world] = (int)$args[0];
                $this->getPlugin()->getListener()->setespawns[$name][$world] = (int)$args[1];
                $this->getPlugin()->getArenasCfg()->setNested("$world.spawns", []);
                $this->getPlugin()->getArenasCfg()->setNested("$world.espawns", []);
                $this->getPlugin()->sendMessage("§eSetting §f $args[0] §eas the spawn for the world§f {$sender->getLevel()->getFolderName()} §eReady to go!", $sender);
                return true;
                
            default:
                throw new InvalidCommandSyntaxException();
        }
    }
    /**
     * @param CommandSender $sender
     * @param string $perm
     * @return bool
     */
    private function badPerm(CommandSender $sender, string $perm): bool {
        if (!$sender->hasPermission("murder.command.$perm")){
            $sender->sendMessage(new TranslationContainer(TextFormat::RED . "%commands.generic.permission"));
            return true;
        }
        return false;
    }
    /**
     * @return MurderMain
     */
    public function getPlugin(): Plugin{
        return $this->plugin;
    }
}

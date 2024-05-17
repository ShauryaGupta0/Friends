<?php



namespace ShauryaGupta06\Friends;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerInteractEvent;

class Main extends PluginBase implements Listener {

    public $prefix = "§l§6FRIENDS§r§b »§r ";

    public function onEnable(): void {
        @mkdir($this->getDataFolder());
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getLogger()->info($this->prefix . "§aactivated by ShauryaGupta06");
    }

    public function onJoin(PlayerJoinEvent $event): void {
        $player = $event->getPlayer();
        $name = $player->getName();

        // Initialize player file
        $playerFile = new Config($this->getDataFolder() . $name . ".yml", Config::YAML);
        if (!file_exists($this->getDataFolder() . $name . ".yml")) {
            $playerFile->set("Friend", []);
            $playerFile->set("Invitations", []);
            $playerFile->set("blocked", false);
            $playerFile->save();
        } else {
            $this->handleExistingPlayer($player, $playerFile);
        }
    }

    public function onQuit(PlayerQuitEvent $event): void {
        $player = $event->getPlayer();
        $name = $player->getName();
        $playerFile = new Config($this->getDataFolder() . $name . ".yml", Config::YAML);
        if (!empty($playerFile->get("Friend"))) {
            foreach ($playerFile->get("Friend") as $friend) {
                $friendPlayer = $this->getServer()->getPlayerExact($friend);
                if ($friendPlayer !== null) {
                    $friendPlayer->sendMessage($this->prefix . "§a" . $player->getName() . " §6is offline now");
                }
            }
        }
    }

    public function onCommand(CommandSender $sender, Command $cmd, string $label, array $args): bool {
        if ($cmd->getName() === "friends") {
            if ($sender instanceof Player) {
                $this->handleFriendCommand($sender, $args);
            } else {
                $this->getLogger()->info($this->prefix . "§aThe console has no friends :)");
            }
            return true;
        }
        return false;
    }

    private function handleFriendCommand(Player $sender, array $args): void {
        $playerFile = new Config($this->getDataFolder() . $sender->getName() . ".yml", Config::YAML);

        if (empty($args)) {
            $this->showFriendHelp($sender);
        } else {
            switch ($args[0]) {
                case "invite":
                    if (empty($args[1])) {
                        $sender->sendMessage($this->prefix . "§eUsage: §2/friends invite <player>");
                    } else {
                        $this->invitePlayer($sender, $args[1]);
                    }
                    break;
                case "accept":
                    if (empty($args[1])) {
                        $sender->sendMessage($this->prefix . "§eUsage: §2/friends accept <player>");
                    } else {
                        $this->acceptInvite($sender, $args[1]);
                    }
                    break;
                case "deny":
                    if (empty($args[1])) {
                        $sender->sendMessage($this->prefix . "§eUsage: §2/friends deny <player>");
                    } else {
                        $this->denyInvite($sender, $args[1]);
                    }
                    break;
                case "remove":
                    if (empty($args[1])) {
                        $sender->sendMessage($this->prefix . "§eUsage: §2/friends remove <player>");
                    } else {
                        $this->removeFriend($sender, $args[1]);
                    }
                    break;
                case "list":
                    $this->listFriends($sender);
                    break;
                case "block":
                    $this->toggleBlock($sender);
                    break;
                default:
                    $this->showFriendHelp($sender);
                    break;
            }
        }
    }

    private function showFriendHelp(Player $sender): void {
        $sender->sendMessage("§l§eFRIEND SYSTEM§r");
        $sender->sendMessage("§6/friends §aaccept » §b Accept a friend request");
        $sender->sendMessage("§6/friends §ainvite » §b Send a friend request");
        $sender->sendMessage("§6/friends §alist » §blist your friends");
        $sender->sendMessage("§6/friends §adeny » §bRefuse a friend request");
        $sender->sendMessage("§6/friends §aremove » §bremove a friend");
        $sender->sendMessage("§6/friends §ablock » §fDisable your friend request");
    }

    private function handleExistingPlayer(Player $player, Config $playerFile): void {
        if (!empty($playerFile->get("Invitations"))) {
            foreach ($playerFile->get("Invitations") as $invite) {
                $player->sendMessage($this->prefix . "§a" . $invite . "§r§e is now your friend!");
            }
        }
        if (!empty($playerFile->get("Friend"))) {
            foreach ($playerFile->get("Friend") as $friend) {
                $friendPlayer = $this->getServer()->getPlayerExact($friend);
                if ($friendPlayer !== null) {
                    $friendPlayer->sendMessage($this->prefix . "§a" . $player->getName() . " §eis online now");
                }
            }
        }
    }

    private function invitePlayer(Player $sender, string $targetName): void {
        if (file_exists($this->getDataFolder() . $targetName . ".yml")) {
            $targetFile = new Config($this->getDataFolder() . $targetName . ".yml", Config::YAML);
            if ($targetFile->get("blocked") === false) {
                $invitations = $targetFile->get("Invitations");
                if (!in_array($sender->getName(), $invitations)) {
                    $invitations[] = $sender->getName();
                    $targetFile->set("Invitations", $invitations);
                    $targetFile->save();
                    $sender->sendMessage($this->prefix . "§aYour friend request has been sent to " . $targetName);
                    $targetPlayer = $this->getServer()->getPlayerExact($targetName);
                    if ($targetPlayer !== null) {
                        $targetPlayer->sendMessage("§a" . $sender->getName() . " §rsent you a friend request. Accept it with /friends accept " . $sender->getName() . " or reject it with /friends deny " . $sender->getName() . "§a!");
                    }
                } else {
                    $sender->sendMessage($this->prefix . "§aYou have already sent a friend request to this player.");
                }
            } else {
                $sender->sendMessage($this->prefix . "§aThis player is not accepting friend requests.");
            }
        } else {
            $sender->sendMessage($this->prefix . "§aThis player does not exist.");
        }
    }

    private function acceptInvite(Player $sender, string $targetName): void {
        $playerFile = new Config($this->getDataFolder() . $sender->getName() . ".yml", Config::YAML);
        if (file_exists($this->getDataFolder() . $targetName . ".yml")) {
            $targetFile = new Config($this->getDataFolder() . $targetName . ".yml", Config::YAML);
            if (in_array($targetName, $playerFile->get("Invitations"))) {
                $invitations = $playerFile->get("Invitations");
                unset($invitations[array_search($targetName, $invitations)]);
                $playerFile->set("Invitations", $invitations);
                $friends = $playerFile->get("Friend");
                $friends[] = $targetName;
                $playerFile->set("Friend", $friends);
                $playerFile->save();

                $targetFriends = $targetFile->get("Friend");
                $targetFriends[] = $sender->getName();
                $targetFile->set("Friend", $targetFriends);
                $targetFile->save();

                $targetPlayer = $this->getServer()->getPlayerExact($targetName);
                if ($targetPlayer !== null) {
                    $targetPlayer->sendMessage($this->prefix . "§a" . $sender->getName() . " §eaccepted your friend request");
                }
                $sender->sendMessage($this->prefix . "§a" . $targetName . " is now your friend");
            } else {
                $sender->sendMessage($this->prefix . "§aThis player did not send you a friend request");
            }
        } else {
            $sender->sendMessage($this->prefix . "§aThis player does not exist");
        }
    }

    private function denyInvite(Player $sender, string $targetName): void {
        $playerFile = new Config($this->getDataFolder() . $sender->getName() . ".yml", Config::YAML);
        if (file_exists($this->getDataFolder() . $targetName . ".yml")) {
            $targetFile = new Config($this->getDataFolder() . $targetName . ".yml", Config::YAML);
            if (in_array($targetName, $playerFile->get("Invitations"))) {
                $invitations = $playerFile->get("Invitations");
                unset($invitations[array_search($targetName, $invitations)]);
                $playerFile->set("Invitations", $invitations);
                $playerFile->save();
                $sender->sendMessage($this->prefix . "§aThe request of " . $targetName . "§e was rejected");
            } else {
                $sender->sendMessage($this->prefix . "§cThis player did not send you a friend request");
            }
        } else {
            $sender->sendMessage($this->prefix . "§cThis player does not exist");
        }
    }

    private function removeFriend(Player $sender, string $targetName): void {
        $playerFile = new Config($this->getDataFolder() . $sender->getName() . ".yml", Config::YAML);
        if (file_exists($this->getDataFolder() . $targetName . ".yml")) {
            $targetFile = new Config($this->getDataFolder() . $targetName . ".yml", Config::YAML);
            if (in_array($targetName, $playerFile->get("Friend"))) {
                $friends = $playerFile->get("Friend");
                unset($friends[array_search($targetName, $friends)]);
                $playerFile->set("Friend", $friends);
                $playerFile->save();

                $targetFriends = $targetFile->get("Friend");
                unset($targetFriends[array_search($sender->getName(), $targetFriends)]);
                $targetFile->set("Friend", $targetFriends);
                $targetFile->save();

                $sender->sendMessage($this->prefix . "§a" . $targetName . " §bis no longer your friend");
            } else {
                $sender->sendMessage($this->prefix . "§aThis player is not your friend");
            }
        } else {
            $sender->sendMessage($this->prefix . "§aThis player does not exist");
        }
    }

    private function listFriends(Player $sender): void {
        $playerFile = new Config($this->getDataFolder() . $sender->getName() . ".yml", Config::YAML);
        if (empty($playerFile->get("Friend"))) {
            $sender->sendMessage($this->prefix . "§aYou have no friends");
        } else {
            $sender->sendMessage("§l§RYOUR CURRENT FRIENDS§7:§r§b");
            foreach ($playerFile->get("Friend") as $friend) {
                if ($this->getServer()->getPlayerExact($friend) === null) {
                    $sender->sendMessage("§e" . $friend . " » §7(§coffline§7)");
                } else {
                    $sender->sendMessage("§e" . $friend . " » §7(§aonline§7)");
                }
            }
        }
    }

    private function toggleBlock(Player $sender): void {
        $playerFile = new Config($this->getDataFolder() . $sender->getName() . ".yml", Config::YAML);
        if ($playerFile->get("blocked") === false) {
            $playerFile->set("blocked", true);
            $playerFile->save();
            $sender->sendMessage($this->prefix . "§aYou will no longer receive friend requests");
        } else {
            $playerFile->set("blocked", false);
            $playerFile->save();
            $sender->sendMessage($this->prefix . "§aYou will now receive friend requests again");
        }
    }

    public function onChat(PlayerChatEvent $event): void {
        $player = $event->getPlayer();
        $msg = $event->getMessage();
        $playerFile = new Config($this->getDataFolder() . $player->getName() . ".yml", Config::YAML);
        $words = explode(" ", $msg);
        if (in_array(str_replace("@", "", $player->getName()), $playerFile->get("Friend"))) {
            $friend = $this->getServer()->getPlayerExact(str_replace("@", "", $player->getName()));
            if ($friend !== null) {
                $friend->sendMessage($this->prefix . " §7[§e" . str_replace("@", "", $player->getName()) . "§7] §l>>§r " . str_replace($words[0], "", $msg));
                $player->sendMessage($this->prefix . " §7[§e" . str_replace("@", "", $player->getName()) . "§7] §l>>§r " . str_replace($words[0], "", $msg));
            } else {
                $player->sendMessage($this->prefix . "§c" . str_replace("@", "", $player->getName()) . " is not online!");
            }
            $event->cancel();
        }
    }
}

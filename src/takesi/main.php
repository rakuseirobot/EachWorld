<?php

namespace takesi;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityLevelChangeEvent;
use pocketmine\event\entity\EntitySpawnEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\level\Position;
use pocketmine\network\mcpe\protocol\LoginPacket;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use pocketmine\utils\Config;
use takesi\EachTask;
use tokyo\pmmp\libform\element\Button;
use tokyo\pmmp\libform\FormApi;

class main extends PluginBase implements Listener
{

    public $player_touch_time = array();
    protected $PlayerData;

    public $form_data = array();

    public function onEnable()
    {
        $this->getLogger()->notice("これはtakesiによる自作プラグインです。");
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getScheduler()->scheduleRepeatingTask(new EachTask($this), 10);
        if (!file_exists($this->getDataFolder())) {
            mkdir($this->getDataFolder(), 0744, true);
        }
        FormAPI::register($this);
    }

    public function callback($player, $response): void
    {
        if (FormApi::FormCancelled($response)) {
            // formがキャンセルされていれば
            //$this->getLogger()->info("form was cancelled.");
            unset($this->form_data[$player->getName()]);
        } else {
            // formがキャンセルされていなければ
            //var_dump($response);
            switch ($response) {
                case 0:
                    $player->sendMessage("§l§eワールド管理システム>>自分のワールドに戻っています...");
                    $this->goLevel($player, $this->getServer()->getLevelByName($player->getName()), $player->getName());
                    $this->getLogger()->info($player->getName()."は自分のワールドに移動しています");
                    break;
                case 1:
                    $player->sendMessage("§l§eワールド管理システム>>ロビー(world)に戻っています...");
                    $this->goLevel($player, $this->getServer()->getLevelByName("world"), $player->getName());
                    $this->getLogger()->info($player->getName()."はロビーに移動しています");
                    break;
                default:
                    $tap_position = $response - 2;
                    //$this->getLogger()->info($tap_position);
                    $name = $this->form_data[$player->getName()][$tap_position];
                    //$this->getLogger()->info($name);
                    $this->getLogger()->info($player->getName()."は".$name."さんのワールドに移動しています");
                    $player->sendMessage("§l§eワールド管理システム>>" . $name . "さんのワールドに移動しています...");
                    $this->goLevel($player, $this->getServer()->getLevelByName($name), $name);
                    break;
            }
            unset($this->form_data[$player->getName()]);
        }
    }

    public function onPacketReceived(DataPacketReceiveEvent $receiveEvent)
    {
        $pk = $receiveEvent->getPacket();
        if ($pk instanceof LoginPacket) {
            $this->PlayerData[$pk->username] = $pk->clientData;
        }
    }

    public function onJoin(PlayerJoinEvent $event)
    {
        $player = $event->getPlayer();
        if ($this->exsistlevel($player->getName())) {
            if (!file_exists($this->getDataFolder() . $player->getName() . ".yml")) {
                new Config($this->getDataFolder() . $player->getName() . ".yml", Config::YAML, array(
                    'spawn_point_x' => 0,
                    'spawn_point_y' => 10,
                    'spawn_point_z' => 0,
                    'time_set' => 4000,
                    'time_stop' => true,
                    'weather' => 0,
                ));
            }
            $this->config = new Config($this->getDataFolder() . $player->getName() . ".yml", Config::YAML);
            if (!$this->config->exists("allow_attack")) {
                $this->config->set("allow_attack", false);
                $this->config->save();
            }
            //$player->teleport(new Position(-1, 8, 2, $this->getServer()->getDefaultLevel()));//for old world
            $player->teleport(new Position(250,7,579,$this->getServer()->getDefaultLevel())); //for old world
            $player->setGamemode(0);
            $player->sendMessage("[§eSYSTEM§r] " . $player->getName() . "さん、おかえり！建築楽しんでね！");
            $cdata = $this->PlayerData[$player->getName()];
            $os = ["Unknown", "Android", "iOS", "macOS", "FireOS", "GearVR", "HoloLens", "Windows 10", "Windows", "Dedicated", "Orbis", "NX"];
            $os_name = $os[$cdata["DeviceOS"]];
            $player->sendMessage("[§eSYSTEM§r] 現在お使いの端末 : " . $os_name);
        } else {
            if (!file_exists($this->getDataFolder() . $player->getName() . ".yml")) {
                new Config($this->getDataFolder() . $player->getName() . ".yml", Config::YAML, array(
                    'spawn_point_x' => 0,
                    'spawn_point_y' => 10,
                    'spawn_point_z' => 0,
                    'time_set' => 4000,
                    'time_stop' => true,
                    'weather' => 0,
                ));
            }
            $this->getServer()->generateLevel($player->getName());
            $this->getServer()->loadLevel($player->getName());
            $player->teleport(new Position(250, 7, 579, $this->getServer()->getDefaultLevel()));
            $player->setGamemode(0);
            $player->sendMessage("[§eSYSTEM§r] 生徒サーバーへようこそ");
            $player->sendMessage("[§eSYSTEM§r] このサーバーは§b建築サーバー§rです！");
            $players = Server::getInstance()->getOnlinePlayers();
            foreach ($players as $player) {
                if ($player->isOp()) {
                    $player->sendMessage("[§eSYSTEM§r] 初見と思われる" . $player->getName() . "さんがサーバーに来ました");
                }
            }
        }
    }

    public function onBreak(BlockBreakEvent $event)
    {
        $player = $event->getPlayer();
        $level = $player->getLevel();
        $block = $event->getBlock();
        if (!($player->getName() == $level->getName())) {
            if (!$player->isOp()) {
                $this->config = new Config($this->getDataFolder() . $level->getName() . ".yml", Config::YAML);
                if (!($this->config->exists("invited_" . $player->getName()))) {
                    if ($block->getID() == 78) {
                        if (!($player->getInventory()->getItemInHand()->getID() == 277)) {
                            $player->sendMessage("§l§cワールド管理システム>>破壊権限がありません。");
                            $event->setCancelled();
                        }
                    } else {
                        $player->sendMessage("§l§cワールド管理システム>>破壊権限がありません。");
                        $event->setCancelled();
                    }
                }else{
                    $viewonly = false;
                    if ($this->config->exists("viewonly")) {
                        $viewonly = $this->config->get("viewonly");
                    }
                    if ($viewonly) {
                        $player->sendMessage("§l§cワールド管理システム>>viewonlyが有効です。");
                        $event->setCancelled();
                    }
                }
            }
        }
    }

    public function onPlace(BlockPlaceEvent $event)
    {
        $player = $event->getPlayer();
        $level = $player->getLevel();
        $block = $event->getBlock();
        if ($player->getName() != $level->getName()) {
            if (!$player->isOp()) {
                $this->config = new Config($this->getDataFolder() . $level->getName() . ".yml", Config::YAML);
                if ($this->config->exists("invited_" . $player->getName())) {
                    $viewonly = false;
                    if ($this->config->exists("viewonly")) {
                        $viewonly = $this->config->get("viewonly");
                    }
                    if (!$viewonly) {
                        $this->getLogger()->debug("ID : " . $block->getID());
                        switch ($block->getID()) {
                            case 8:
                            case 9:
                            case 10:
                            case 11:
                            case 46:
                            case 79:
                                $player->sendMessage("§l§cワールド管理システム>>設置権限がありません。");
                                $event->setCancelled();
                                break;
                        }
                        $this->getLogger()->debug("ItemInHand : " . $player->getInventory()->getItemInHand()->getID());
                        switch ($player->getInventory()->getItemInHand()->getID()) {
                            case 259:
                            case 326:
                            case 327:
                                $player->sendMessage("§l§cワールド管理システム>>設置権限がありません。");
                                $event->setCancelled();
                                break;
                        }
                    }else{
                        $player->sendMessage("§l§cワールド管理システム>>viewonlyが有効です。");
                        $event->setCancelled();
                    }
                } else {
                    $player->sendMessage("§l§cワールド管理システム>>設置権限がありません。");
                    $event->setCancelled();
                }
            } else {
                switch ($block->getID()) {
                    case 8:
                    case 9:
                    case 10:
                    case 11:
                    case 46:
                    case 79:
                        $player->sendMessage("§l§cワールド管理システム>>設置権限がありません。");
                        foreach ($this->getServer()->getOnlinePlayers() as $player_tmp) {
                            $player_tmp->sendMessage("§l§c警告>>管理者権限を持つ" . $player->getName() . "が" . $level->getName() . "のワールドで禁止指定アイテムを置こうとしました");
                        }
                        $event->setCancelled();
                        break;
                }
            }
        }
    }

    public function onTap(PlayerInteractEvent $event)
    {
        $item = $event->getItem();
        $player = $event->getPlayer();
        if ($player->getInventory()->getItemInHand()->getId() == 0) {
            if (isset($this->player_touch_time[$player->getName()])) {
                if ($this->player_touch_time[$player->getName()] + 2 > time()) {
                    $list = FormAPI::makeListForm([$this, "callback"]);
                    $list = $list->setTitle("ワールドメニュー")
                        ->setContent("次はどこに行く？")
                        ->addButton((new Button("自分のワールドへ")))
                        ->addButton((new Button("ロビーへ")));
                    $players = Server::getInstance()->getOnlinePlayers();
                    if (!isset($this->form_data[$player->getName()])) {
                        $this->form_data[$player->getName()] = [];
                        $pointer = 0;
                        foreach ($players as $player1) {
                            $list = $list->addButton((new Button($player1->getName() . "のワールドへ")));
                            $this->form_data[$player->getName()][$pointer] = $player1->getName();
                            $pointer++;
                        }
                        $list->sendToPlayer($player);
                    }
                    unset($this->player_touch_time[$player->getName()]);
                } else {
                    $this->player_touch_time[$player->getName()] = time();
                }
            } else {
                $this->player_touch_time[$player->getName()] = time();
            }
        }
        if ($player->getName() != $player->getLevel()->getName()) {
            switch ($item->getID()) {
                case 259:
                case 325:
                    $player->sendMessage("§l§cワールド管理システム>>設置権限がありません。");
                    $event->setCancelled();
                    break;
            }
        }
        $this->getLogger()->debug("PlayerName : " . $player->getName() . " LevelName : " . $player->getLevel()->getName() . "ItemName : " . $item->getName() . " ItemID : " . $item->getID() . " Action : " . $event->getAction());
    }

    public function onLevelChange(EntityLevelChangeEvent $event)
    {
        $this->config = new Config($this->getDataFolder() . $event->getTarget()->getName() . ".yml", Config::YAML);
        if ($this->config->exists("baneed_" . $event->getEntity()->getName())) {
            $event->getEntity()->sendMessage("§l§cワールド管理システム>>ワールドBanされているため行くことができません。");
            $event->setCancelled();
        } else {
            if ($event->getEntity()->getName() == $event->getTarget()->getName()) {
                $event->getEntity()->setGamemode(1);
            }
        }
    }

    public function onDamage(EntityDamageEvent $event)
    {
        if ($event->getEntity() instanceof Player) {
            $this->config = new Config($this->getDataFolder() . $event->getEntity()->getLevel()->getName() . ".yml", Config::YAML);
            if (!$this->config->get("allow_attack")) {
                $event->setCancelled();
            }
        }
    }

    public function onSpawn(EntitySpawnEvent $event)
    {
        $event->getEntity()->kill();
    }

    public function goLevel($player, $targetlevel, $name)
    {
        $this->config = new Config($this->getDataFolder() . $name . ".yml", Config::YAML);
        if (!$this->getServer()->isLevelLoaded($name)) {
            $this->getServer()->loadLevel($name);
        } else {
            $targetlevel->setTime($this->config->get("time_set"));
            if ($this->config->get("time_stop")) {
                $targetlevel->stopTime();
            }
        }
        $player->teleport(new Position($this->config->get("spawn_point_x"), $this->config->get("spawn_point_y"), $this->config->get("spawn_point_z"), $targetlevel));
        //$targetlevel->getWeather()->setWeather($this->config->get("weather"));

        if ($player->getName() != $targetlevel->getName()) {
            if ($this->getServer()->getPlayerExact($targetlevel->getName()) != null) {
                $this->getServer()->getPlayerExact($targetlevel->getName())->sendMessage("§l§9通知>>§6" . $player->getName() . "さん§9があなたのワールドに来ました！");
            }
        }
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
    {
        switch ($command->getName()) {
            case "wo":
                if (!isset($args[0])) {
                    $sender->sendMessage("====Worldコマンドの使用方法======");
                    $sender->sendMessage("/wo me: 自分のワールドに移動します");
                    $sender->sendMessage("/wo **: **のワールドに移動します");
                    $sender->sendMessage("/wo random: ランダムで他のワールドに移動します");
                    $sender->sendMessage("/wo q * : ワールドの検索を行います");
                    $sender->sendMessage("/wo clear : ワールド内のプレイヤーのインベントリを初期化します");
                    $sender->sendMessage("/wo list: ワールド内のプレイヤー一覧を表示します");
                    $sender->sendMessage("/wo s: ワールドの詳細設定をします");
                    $sender->sendMessage("/wo gm 0~3 : 自分のゲームモードの変更をします");
                    $sender->sendMessage("/wo give ** : **に自分が今持っているアイテムを渡します");
                    $sender->sendMessage("/wo invite **: **にワールドの編集権限を与えます");
                    $sender->sendMessage("/wo invitelist : ワールドの編集権限を与えてる人を一覧表示します");
                    $sender->sendMessage("/wo uninvite **: **の編集権限を剥奪します");
                    $sender->sendMessage("/wo uninvite all: ワールドの編集権限を与えてる人全員の権限を剥奪します");
                    $sender->sendMessage("/wo kick **: **をワールドからkickします");
                    $sender->sendMessage("/wo ban **: **をワールドからBanします");
                    $sender->sendMessage("/wo unban **: **のワールドBanを解除します");
                    $sender->sendMessage("/wo banlist : ワールドBanしたプレイヤーの一覧");
                } else {
                    switch ($args[0]) {
                        case "me":
                            if ($sender instanceof Player) {
                                $sender->sendMessage("§l§eワールド管理システム>>自分のワールドに戻っています...");
                                $this->goLevel($sender, $this->getServer()->getLevelByName($sender->getName()), $sender->getName());
                            } else {
                            }
                            return true;
                        case "q":
                            if (!isset($args[1])) {
                                $sender->sendMessage("検索文字列を指定してください");
                            } else {
                                $sender->sendMessage("====検索結果======");
                                if ($dir = opendir($this->getServer()->getFilePath() . "worlds")) {
                                    while (($file = readdir($dir)) !== false) {
                                        if ($file != "." && $file != ".." && str_replace($args[1], "tttttt", $file) != $file) {
                                            $sender->sendMessage(str_replace($args[1], "§c" . $args[1] . "§r", $file));
                                        }
                                    }
                                    closedir($dir);
                                }
                            }
                            return true;
                        case "list":
                            $players = $this->getServer()->getLevelByName($sender->getName())->getPlayers();
                            if (sizeof($players) == 0) {
                                $sender->sendMessage("§l§eワールド管理システム>>あなたのワールドには誰もいません");
                            } else {
                                $tmp = "§l§eワールド管理システム>>あなたのワールドには、";
                                $first = true;
                                foreach ($players as $player) {
                                    if ($first) {
                                        $tmp = $tmp . $player->getName();
                                        $first = false;
                                    } else {
                                        $tmp = $tmp . "," . $player->getName();
                                    }
                                }
                                $tmp = $tmp . "の" . sizeof($players) . "名がいます";
                                $sender->sendMessage($tmp);
                            }
                            return true;
                        case "random":
                            $dir = $this->getServer()->getFilePath() . "worlds/";
                            $fileList = array();
                            foreach ($this->getServer()->getLevels() as $level) {
                                array_push($fileList, $level->getName());
                            }
                            $name = $fileList[array_rand($fileList)];
                            $sender->sendMessage("§l§eワールド管理システム>>" . $name . "のワールドへ移動します。");
                            if ($this->exsistlevel($name)) {
                                $this->goLevel($sender, $this->getServer()->getLevelByName($name), $sender->getName());
                            } else {
                                $sender->sendMessage("エラー");
                            }
                            return true;
                        case "give":
                            if ($sender->getName() == $sender->getLevel()->getName()) {
                                if (!isset($args[1])) {
                                    $sender->sendMessage("§l§eワールド管理システム>>相手の名前を指定して打ち直してください");
                                } else {
                                    if ($this->getServer()->getPlayerExact($args[1]) == null) {
                                        $sender->sendMessage("§l§eワールド管理システム>>指定されたプレイヤーが見つかりませんでした");
                                    } else {
                                        if ($this->getServer()->getPlayerExact($args[1])->getLevel()->getName() == $sender->getLevel()->getName()) {
                                            if ($sender->getInventory()->getItemInHand() == null) {
                                                $sender->sendMessage("§l§eワールド管理システム>>渡したいアイテムを持ってください");
                                            } else {
                                                $this->getServer()->getPlayerExact($args[1])->getInventory()->addItem($sender->getInventory()->getItemInHand());
                                                $sender->sendMessage("§l§eワールド管理システム>>成功！！");
                                            }
                                        } else {
                                            $sender->sendMessage("§l§eワールド管理システム>>相手が違うワールドにいるので出来ません");
                                        }
                                    }
                                }
                            } else {
                                $sender->sendMessage("§l§eワールド管理システム>>他人のワールドで使用することはできません");
                            }
                            return true;
                        case "clear":
                            if ($sender->getName() == $sender->getLevel()->getName()) {
                                $players = $sender->getLevel()->getPlayers();
                                foreach ($players as $player) {
                                    if ($player->getName() != $sender->getName()) {
                                        $player->getInventory()->clearAll();
                                        $player->sendMessage("§l§eワールド管理システム>>ワールドの管理者によってインベントリは初期化されました");
                                    }
                                }
                                $sender->sendMessage("§l§eワールド管理システム>>成功！！");
                            } else {
                                $sender->sendMessage("§l§eワールド管理システム>>他人のワールドで使用することはできません");
                            }
                        case "s":
                            if (!isset($args[1])) {
                                $sender->sendMessage("-===World詳細設定コマンドの使用方法======");
                                $sender->sendMessage("/wo s setspawn: 自分のワールドのワールドのスポーン地点をセットします");
                                $sender->sendMessage("/wo s pvp (on|off): 自分のワールドPVPを有効にするか無効にするかをセットします。");
                                $sender->sendMessage("/wo s settime **: 自分のワールドの時間を**にセットします");
                                $sender->sendMessage("/wo s stoptime: 自分のワールドの時間を固定します");
                                $sender->sendMessage("/wo s restarttime: 自分のワールドの時間の固定を解除します");
                                $sender->sendMessage("/wo s setweather **: 自分のワールドの天候を**(0から2)で固定します");
                                $sender->sendMessage("/wo s viewonly (on|off): 閲覧限定モードを有効にするか無効にするかをセットします。");
                            } else {
                                switch ($args[1]) {
                                    case "setspawn":
                                        $this->config = new Config($this->getDataFolder() . $sender->getName() . ".yml", Config::YAML);
                                        $this->config->set("spawn_point_x", $sender->getX());
                                        $this->config->set("spawn_point_y", $sender->getY() + 2);
                                        $this->config->set("spawn_point_z", $sender->getZ());
                                        $this->config->save();
                                        $sender->sendMessage("スポーン地点を X>" . $sender->getX() . " Y>" . $sender->getY() . " Z>" . $sender->getZ() . "に設定しました。");
                                        return true;
                                    case "pvp":
                                        $this->config = new Config($this->getDataFolder() . $sender->getName() . ".yml", Config::YAML);
                                        if (!isset($args[2])) {
                                            $sender->sendMessage("PVPを有効にするか無効にするかを指定してください。");
                                        } else {
                                            if ($this->getReturnFromString($args[2])) {
                                                $this->config->set("allow_attack", true);
                                                $sender->sendMessage("§l§eワールド管理システム>>PVPを有効にしました。");
                                                $this->config->save();
                                            } else {
                                                $this->config->set("allow_attack", false);
                                                $sender->sendMessage("§l§eワールド管理システム>>PVPを無効にしました。");
                                                $this->config->save();
                                            }
                                        }
                                        return true;

                                    case "viewonly":
                                        if (!isset($args[2])) {
                                            $sender->sendMessage("§l§eワールド管理システム>>/wo s viewonly (on|off)");
                                        } else {
                                            $this->config = new Config($this->getDataFolder() . $sender->getName() . ".yml", Config::YAML);
                                            $this->config->set("viewonly", $this->getReturnFromString($args[2]));
                                            $this->config->save();
                                            if ($this->getReturnFromString($args[2])) {
                                                $sender->sendMessage("§l§eワールド管理システム>>viewonlyモードを有効にしました。");
                                            } else {
                                                $sender->sendMessage("§l§eワールド管理システム>>viewonlyモードを無効しました。");
                                            }
                                        }
                                        return true;

                                    case "settime":
                                        $this->config = new Config($this->getDataFolder() . $sender->getName() . ".yml", Config::YAML);
                                        if (!isset($args[2])) {
                                            $sender->sendMessage("セットしたい時間を指定してください。");
                                        } else {
                                            $this->config->set("time_set", $args[2]);
                                            $this->config->save();
                                            $sender->sendMessage("§l§eワールド管理システム>>時間の設定変更完了！");
                                            $this->getServer()->getLevelByName($sender->getName())->setTime($args[2]);
                                        }
                                        return true;
                                    case "stoptime":
                                        $this->config = new Config($this->getDataFolder() . $sender->getName() . ".yml", Config::YAML);
                                        $this->config->set("time_stop", true);
                                        $this->config->save();
                                        $sender->sendMessage("§l§eワールド管理システム>>時間を固定させました！");
                                        $this->getServer()->getLevelByName($sender->getName())->stopTime();
                                        return true;
                                    case "restarttime":
                                        $this->config = new Config($this->getDataFolder() . $sender->getName() . ".yml", Config::YAML);
                                        $this->config->set("time_stop", false);
                                        $this->config->save();
                                        $sender->sendMessage("§l§eワールド管理システム>>時間をリスタートさせました！");
                                        $this->getServer()->getLevelByName($sender->getName())->startTime();
                                        return true;
                                    case "setweather":
                                        $sender->sendMessage("§l§eワールド管理システム>>このコマンドはサーバーソフトの変更により、廃止しざる負えなくなりました");
                                        /*$this->config = new Config($this->getDataFolder().$sender->getName().".yml", Config::YAML);
                                        if(!isset($args[2])){
                                        $sender->sendMessage("/wo s setweather **: 自分のワールドの天候を**(0から2)で固定します");
                                        }else{
                                        $this->config->set("weather",$args[2]);
                                        $this->config->save();
                                        $sender->sendMessage("§l§eワールド管理システム>>天候の設定変更完了！");
                                        $this->getServer()->getLevelByName($sender->getName())->getWeather()->setWeather($this->config->get("weather"));
                                        }*/
                                        return true;
                                }
                            }
                            return true;
                        case "gm":
                            if (!isset($args[1])) {
                                $sender->sendMessage("§l§eワールド管理システム>>ゲームモードを0から3で指定してください。");
                            } else {
                                if ($sender->getName() == $sender->getLevel()->getName()) {
                                    $sender->setGamemode($args[1]);
                                    $sender->sendMessage("§l§eワールド管理システム>>ゲームモードを" . $args[1] . "に変更しました。");
                                } else {
                                    if (!$sender->isOp()) {
                                        $this->config = new Config($this->getDataFolder() . $sender->getLevel()->getName() . ".yml", Config::YAML);
                                        if ($this->config->exists("invited_" . $sender->getName())) {
                                            $sender->setGamemode($args[1]);
                                            $sender->sendMessage("§l§eワールド管理システム>>ゲームモードを" . $args[1] . "に変更しました。");
                                        } else {
                                            $sender->sendMessage("§l§eワールド管理システム>>他人のワールドで使用することはできません。");
                                        }
                                    } else {
                                        $sender->setGamemode($args[1]);
                                        $sender->sendMessage("§l§eワールド管理システム>>ゲームモードを" . $args[1] . "に変更しました。");
                                    }
                                }
                            }
                            return true;
                        case "invite":
                            if (!isset($args[1])) {
                                $sender->sendMessage("§l§eワールド管理システム>>招待する人を指定してください。");
                            } else {
                                $this->config = new Config($this->getDataFolder() . $sender->getName() . ".yml", Config::YAML);
                                $this->config->set("invited_" . $args[1], true);
                                $this->config->save();
                                $sender->sendMessage("§l§eワールド管理システム>>" . $args[1] . "さんを招待しました。");
                            }
                            return true;
                        case "invitelist":
                            $this->config = new Config($this->getDataFolder() . $sender->getName() . ".yml", Config::YAML);
                            $sender->sendMessage("§l§eワールド管理システム>>あなたがワールドの編集権限を与えてる(invite)してる人");
                            $pointer = 0;
                            foreach ($this->config->getAll() as $key => $value) {
                                $this->getLogger()->info($key);
                                if (strpos($key, 'invited_') !== false) {
                                    $pointer++;
                                    $a = str_replace("invited_", "", $key);
                                    $sender->sendMessage($pointer . "人目 " . $a);
                                }
                            }
                            return true;
                        case "uninvite":
                            if (!isset($args[1])) {
                                $sender->sendMessage("§l§eワールド管理システム>>権限を剥奪する人を指定してください。");
                            } else {
                                $this->config = new Config($this->getDataFolder() . $sender->getName() . ".yml", Config::YAML);
                                if ($args[1] == "all") {
                                    $pointer = 0;
                                    foreach ($this->config->getAll() as $key => $value) {
                                        $this->getLogger()->info($key);
                                        if (strpos($key, 'invited_') !== false) {
                                            $pointer++;
                                            $this->config->remove($key);
                                        }
                                    }
                                    $this->config->save();
                                    $sender->sendMessage("§l§eワールド管理システム>>合計で" . $pointer . "人の権限を外しました");
                                } else {
                                    if ($this->config->exists("invited_" . $args[1])) {
                                        $this->config->remove("invited_" . $args[1]);
                                        $this->config->save();
                                        $sender->sendMessage("§l§eワールド管理システム>>" . $args[1] . "の権限を剥奪しました。");
                                    } else {
                                        $sender->sendMessage("§l§eワールド管理システム>>" . $args[1] . "さんにはもともとワールド編集許可が与えられていません。");
                                    }
                                }
                            }
                            return true;
                        case "kick":
                            if ($sender->getName() == $sender->getLevel()->getName()) {
                                if (!isset($args[1])) {
                                    $sender->sendMessage("§l§eワールド管理システム>>Kickする人を指定してください。");
                                } else {
                                    $players = $sender->getLevel()->getPlayers();
                                    foreach ($players as $player) {
                                        if ($player->getName() == $args[1]) {
                                            $this->goLevel($player, $this->getServer()->getLevelByName($player->getName()), $player->getName());
                                            $player->kick("ワールドの管理者によりKickされました。", false);
                                            $sender->sendMessage("§l§eワールド管理システム>>" . $args[1] . "をKickしました。");
                                        }
                                    }
                                }
                            } else {
                                $sender->sendMessage("§l§eワールド管理システム>>自分のワールドで使用してください。");
                            }
                            return true;
                        case "ban":
                            if ($sender->getName() == $sender->getLevel()->getName()) {
                                if (!isset($args[1])) {
                                    $sender->sendMessage("§l§eワールド管理システム>>ワールドBanする人を指定してください。");
                                } else {
                                    if ($sender->getName() == $args[1]) {
                                        $sender->sendMessage("§l§eワールド管理システム>>自分をワールドBanすることはできません。");
                                    } else {
                                        $players = $sender->getLevel()->getPlayers();
                                        foreach ($players as $player) {
                                            if ($player->getName() == $args[1]) {
                                                $this->goLevel($player, $this->getServer()->getLevelByName($player->getName()), $player->getName());
                                                $player->kick("ワールドの管理者によりワールドBanされました。", false);
                                                $sender->sendMessage("§l§eワールド管理システム>>" . $args[1] . "をワールドBanしました。");
                                            }
                                        }
                                        $this->config = new Config($this->getDataFolder() . $sender->getName() . ".yml", Config::YAML);
                                        $this->config->set("baneed_" . $args[1], true);
                                        $this->config->save();
                                    }
                                }
                            } else {
                                $sender->sendMessage("§l§eワールド管理システム>>自分のワールドで使用してください。");
                            }
                            return true;
                        case "banlist":
                            if ($sender->getName() == $sender->getLevel()->getName()) {
                                $this->config = new Config($this->getDataFolder() . $sender->getName() . ".yml", Config::YAML);
                                $sender->sendMessage("Banしている人の一覧");
                                foreach ($this->config->getAll() as $key) {
                                    if (strpos($key, 'baneed_') !== false) {
                                        $a = str_replace("baneed_", "", $key);
                                        $sender->sendMessage($a);
                                    }
                                }
                            } else {
                                $sender->sendMessage("§l§eワールド管理システム>>自分のワールドで使用してください。");
                            }
                            return true;
                        case "unban":
                            if ($sender->getName() == $sender->getLevel()->getName()) {
                                if (!isset($args[1])) {
                                    $sender->sendMessage("§l§eワールド管理システム>>ワールドBanを解除する人を指定してください。");
                                } else {
                                    $this->config = new Config($this->getDataFolder() . $sender->getName() . ".yml", Config::YAML);
                                    if ($this->config->exists("baneed_" . $args[1])) {
                                        $this->config->remove("baneed_" . $args[1]);
                                        $this->config->save();
                                        $sender->sendMessage("§l§eワールド管理システム>>" . $args[1] . "のワールドBanを解除しました。");
                                    } else {
                                        $sender->sendMessage("§l§eワールド管理システム>>その人はワールドBanをされていません。");
                                    }
                                }
                            } else {
                                $sender->sendMessage("§l§eワールド管理システム>>自分のワールドで使用してください。");
                            }
                            return true;
                        default:
                            if ($sender instanceof Player) {
                                if ($args[0] != "") {
                                    if ($this->exsistlevel($args[0])) {
                                        $sender->sendMessage("§l§eワールド管理システム>>" . $args[0] . "さんのワールドに移動しています...");
                                        $this->goLevel($sender, $this->getServer()->getLevelByName($args[0]), $args[0]);
                                    } else {
                                        $sender->sendMessage("§l§eワールド管理システム>>" . $args[0] . "のワールドは存在しません。");
                                    }
                                } else {
                                    $sender->sendMessage("§l§eワールド管理システム>>ワールド名を空白にすることはできません");
                                }
                            }
                            return true;
                    }
                }
        }
        return true;
    }

    public function exsistlevel($level_name)
    {
        if (file_exists($this->getServer()->getFilePath() . DIRECTORY_SEPARATOR . "worlds" . DIRECTORY_SEPARATOR . $level_name)) {
            $this->getServer()->loadLevel($level_name);
            return true;
        } else {
            return false;
        }
    }

    public static function getReturnFromString($str)
    {
        switch (strtolower(trim($str))) {
            case "off":
            case "false":
            case "0":
                return false;
            case "on":
            case "true":
            case "1":
                return true;
        }
        return false;
    }
}

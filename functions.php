<?php   // resources: antennaweb.org, rabbitears.com
  
  $startTimeStamp = microtime(true);

  if(php_sapi_name() != 'cli' && $_SERVER['REMOTE_ADDR'] != '10.0.0.1'){
      echo 'IP not allowed: '.$_SERVER['REMOTE_ADDR'];
      exit;
  }
  
  set_time_limit(0);  
  ini_set('implicit_flush', 1);
  ini_set('output_buffering', 'Off');
  set_error_handler('myErrorHandler');
  register_shutdown_function('shutdownFunction');
  setlocale(LC_CTYPE, 'en_US.UTF-8');
  
  $tunerChannelLineUp = '';
  $epg = array('xml'=>'', 'lastLoadedTime'=>'');
  $log = array('path'=>'D:/tv/logs/'.(microtime(true)*10000).'.html', 'echo'=>true); //rename channel name and append data
  
  if(isset($argv) && count($argv) > 1){
      global $log;
      logw('<hr>started log, mode:'.php_sapi_name());
      logw('CLI args: '.var_export($argv,true));
      if(count($argv) > 1){
          if($argv[1] == 'record' && $argv[2] == 'scan' && $argv[3] == 'shows'){
            scanShowsAndRecordCli();
          }
          else if($argv[1] == 'record'){
             recordChannelCli($argv);
          }
      }
  }
  
  if(php_sapi_name() != 'cli' && count($_GET) == 0){
      printHomepage();
  }
  else if (php_sapi_name() == 'cli' && count($argv) == 1){
      printCliUsage();
  }
  else if(isset($_GET) && count($_GET) == 1 && array_keys($_GET)[0] != '' && php_sapi_name() != 'cli'){
      $argv = array();
      $argv[0] = 'c:/xampp/htdocs/tv/functions.php';      
      $argv[1] = array_keys($_GET)[0];
      logw('calling function: '.$argv[1],$silent=true);
  }
  
  if(isset($argv[1]) && in_array(strtolower($argv[1]), get_defined_functions()['user'])){
      $argv[1]();
      logw('calling function: '.$argv[1],$silent=true);
  }
  
  function getMemoryUsage(){
      $output = array();
      exec('wmic process where processid='.getmypid().' get PeakPageFileUsage /format:csv 2>&1',$output);
      
      $keys = str_getcsv($output[1]);
      $vals = str_getcsv($output[2]);
      $assoc = array();
      for($i=0; $i<count($keys); $i++){
          $assoc[$keys[$i]] = $vals[$i];
      }
      
      //echo '<pre>'.print_r($assoc,true).'</pre>';
      return number_format($assoc['PeakPageFileUsage']/1024,1).'MB';
  }
  
  function writeDbStatus($channel, $message){
      $db = mysqli_connect('127.0.0.1', 'root','','dvr');
      $message = mysqli_real_escape_string($db, $message);
      mysqli_query($db, 'insert into dvr.channel_status (channel, status) values ("'.$channel.'", "'.$message.'") on duplicate key update status = "'.$message.'"');
  }
  
  function logw($message, $silent=false){
    global $log;
    if(is_array($message)){
      $message = print_r($message,true);
    }    
    file_put_contents($log['path'], date('Y-m-d h:i:s A -- ' ).' MEM: '.number_format(memory_get_usage(true)/1024/1024,2).'MB -- '.$message.'<br>', FILE_APPEND);
    $channel = substr(strstr($log['path'], '['), 1, -6);
    if(is_numeric($channel)){
        writeDbStatus($channel, $message);
    }
    if($message == ''){
        $message = '(null)';
    }
    if(!$silent && $log['echo'] == true){
      if(php_sapi_name() == 'cli'){
        echo '['.strip_tags($message).']'."\r\n";
      }
      else{
        echo '['.$message.']'."<br>";
      }
    }
  }

  function myErrorHandler($errno, $errstr, $errfile, $errline){
    if (!(error_reporting() & $errno)) {  // This error code is not included in error_reporting
        return;
    }
    $errfile = str_replace('C:\xampp\htdocs','',$errfile);
    logw('<pre>'.print_r(debug_backtrace(),true).'<pre>');
    switch ($errno) {
        case E_USER_ERROR: logw(array("error"=>"error", "message"=>$errstr, "file"=>$errfile, "line"=>$errline)); exit(-1);
        case E_USER_WARNING: logw(array("error"=>"warning", "message"=>$errstr, "file"=>$errfile, "line"=>$errline)); exit(-1);
        case E_USER_NOTICE: logw(array("error"=>"notice", "message"=>$errstr, "file"=>$errfile, "line"=>$errline)); exit(-1);
        default: logw(array("error"=>"unknown", "message"=>$errstr, "file"=>$errfile, "line"=>$errline)); exit(-1);
    }
    return true; // Don't execute PHP internal error handler
  }
  
  function printHomePage(){
      echo printTunersWatching();
      echo 'Processes running: '.count(getProcesses('php')).' | Recording: '.count(getProcesses('ffmpeg')).'<br>';
      echo 'Available Memory: '.getFreeMemory().'MB | Free Disk Space: '.getFreeDiskSpace('D:').'<br>';
      echo '<hr>';
      echo 'Functions: <a href="?recordAllFavoriteChannels" target="local">Record Fav. Channels</a> | ';
      echo '<a href="?closeAllRecordings" target="local">Close All Recordings</a> | ';
      echo '<a href="?restartAllRecordings" target="local">Restart All Recordings</a> | ';
      echo '<a href="?printWhatsOnNow" target="local">Whats On Now?</a> | ';
      echo '<a href="?whatsOnAllTunerChannels" target="local">Whats On all EPG (Tuners)?</a> | ';
      echo '<a href="?detectChannelsAllTuners" target="local">Detect Channels</a> | ';
      echo '<a href="?clearData" target="local">Clear Data</a> | ';
      echo '<a href="?updateElectronicProgramGuide" target="local">Update EPG</a> | ';
      echo '<a href="?deleteEmptySubFolders" target="local">Delete Empty SubFolders</a> | ';
      echo '<iframe name="local" style="width: 100%; height: 75%;" src="?printWhatsOnNow"></iframe>';
  }
  
  function printCliUsage(){
      echo 'Enter command line arguments to use.';
  }
  
  function shutdownFunction(){
    global $startTimeStamp;
    $line = '<hr>run duration: '.number_format(microtime(true)-$startTimeStamp,2).' seconds';
    logw($line,$silent=true);
    echo($line.'<br>');
  }
  
  function getFreeMemory(){
      $output = array();
      exec('wmic OS get FreePhysicalMemory /Value 2>&1', $output, $return);
      $memory = substr($output[2],19);
      return number_format($memory/1024,1);
  }
  
  function getFreeDiskSpace($drive){
      return number_format(disk_free_space($drive)/1024/1024/1024,1).'GB';
  }
  
  function scanShowsAndRecordCli(){
    while(true){
        $scanShows = explode("\r\n", file_get_contents('D:/tv/settings/scanshow.list'));
        logw('<pre>Scan shows: '.print_r($scanShows,true).'</pre>');

        $alreadyTunedChannels = array();
        foreach(getChannelsTuned() as $tunedChannels){
            $alreadyTunedChannels[] = $tunedChannels['number'];
        }
        
        foreach(whatsOnNow() as $channel => $showTitle){
            if(in_array($showTitle, $scanShows)){
                logw('found show '.$showTitle.' currently on epg of tuner channels. channel '.$channel);
                if(!in_array($channel, $alreadyTunedChannels)){
                    $tuner = getTunerWithFreeSlot(); 
                    if($tuner != false){
                        logw('Tuner with free slot: '.$tuner);
                        $output = array();
                        $cmd = 'D:/tv/programs/PSTools/PsExec64.exe -accepteula -nobanner -i 0 -d "c:/xampp/php/php.exe" "c:/xampp/htdocs/tv/functions.php" record '.$channel.' '.$tuner.' noloop 2>&1';
                        logw('Command: '.$cmd);
                        exec($cmd, $output, $return);
                        //sleep(1);
                        logw('<pre>Command output: '.print_r($output,true).'</pre><hr>');
                     }
                     else{
                         logw('no tuners available with free slots');
                     }
                }
                else{
                    logw('already recording channel from scanshow');
                }
            }
            else{
                //logw('show '.$showTitle.' not found on available tuner channels.');
            }
        }
        while(date('s') % 5 != 0){
            sleep(1);
        }
        sleep(1);
    }
  }
  
  function transferLogFile($newLogFile){
      global $log;
      if($newLogFile != pathinfo($log['path'], PATHINFO_BASENAME)){
          $oldLogData = file_get_contents($log['path']);
          unlink($log['path']);
          $log['path'] = pathinfo($log['path'], PATHINFO_DIRNAME).'/'.$newLogFile;
          file_put_contents($log['path'], $oldLogData, FILE_APPEND);
          logw('log file changed to: '.$log['path']);
      }
      else{
          logw('reusing existing log file');
      }
  }
  
  function recordChannelCli($argv){
    global $log;
    logw('record channel cli');
    $mapArg = array(null => 0, 'ACTION' => 1, 'CHANNEL' => 2, 'TUNER' => 3, 'LOOP' => 4);
      if(isset($argv[$mapArg['CHANNEL']])){
          $channel = str_replace('v','',$argv[$mapArg['CHANNEL']]);
          $tuner = null;
          $loop = true;
          
          if(isset($argv[$mapArg['LOOP']]) && $argv[$mapArg['LOOP']] == 'noloop'){
            $loop = false;
          }
          if(isset($argv[$mapArg['TUNER']])){
            $tuner = $argv[$mapArg['TUNER']];
          }                  
          record($channel, $tuner, $loop);
      }
  }
  
  function recordAllFavoriteChannels(){
    $slots = getTunerListPerSlot();
    $channels = array_unique(array_values(getChannelsToRecord()));
    //shuffle($slots);
    logw($slots);
    logw($channels);
    for($i=0; $i<count($slots)-1 && $i<count($channels); $i++){
        $output = array();
        $cmd = 'D:/tv/programs/PSTools/PsExec64.exe -accepteula -nobanner -i 0 -d "c:/xampp/php/php.exe" "c:/xampp/htdocs/tv/functions.php" record '.$channels[$i].' '.$slots[$i].' 2>&1';
        $descriptorspec = array(
           0 => array("pipe", "r"),   // stdin is a pipe that the child will read from
           1 => array("pipe", "w"),   // stdout is a pipe that the child will write to
           2 => array("pipe", "w")    // stderr is a pipe that the child will write to
        );

        $process = proc_open($cmd, $descriptorspec, $pipes);
        //if($i > 0 && $slots[$i] == $slots[$i-1]){
         sleep(1);
        //}
        //exec($cmd, $output, $return);
        //echo print_r($output,true).'<hr>';
    }
  }
  
  function restartAllRecordings(){
      closeAllRecordings();
      sleep(1);
      recordAllFavoriteChannels();
  }
  
  function fetchEpgXml(){
      global $epg;
      if($epg['xml'] == null || (time() - $epg['lastLoadedTime']) > 60*60){
         $epg['xml'] = simplexml_load_file('D:/tv/programs/atsc-guide.xml'); 
         $epg['lastLoadedTime'] = time();
      }
      return $epg['xml'];
  }
  
  function getRandomTuner(){
      $tuners = getTunersOnline();
      return $tuners[rand(0, count($tuners)-1)];
  }
  
  function getChannelLineup(){
      global $tunerChannelLineUp;
      if($tunerChannelLineUp == ''){
        $tunerChannelLineUp = getTunerChannelLineUp(getRandomTuner());
      }
      return $tunerChannelLineUp;
  }
  
  function updateElectronicProgramGuide(){
      $output = array();
      $epgAccount = json_decode(file_get_contents('D:/tv/settings/epgaccount.json'),true);
      chdir('D:/tv/programs/');
      exec('D:/tv/programs/zap2xml.exe -u '.$epgAccount['username'].' -p '.$epgAccount['password'].' -o D:/tv/programs/atsc-guide.xml -d 2 -U 2>&1', $output, $return);
      $result = '<pre>'.print_r($output, true).'<pre>';
      echo $result;
      transferLogFile('epg_'.date('Y-m-d').'.html');
  }
  
  function http_get_contents($address){
    $ch = curl_init($address);
    curl_setopt_array($ch, [CURLOPT_TIMEOUT => 1, CURLOPT_RETURNTRANSFER => true]);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
  }
  
  function getTunersOnline(){
      $tuners = explode("\r\n", file_get_contents('D:/tv/settings/tuners.list'));
      $online = [];
      $ch = curl_init();
      foreach($tuners as $tuner){
          curl_setopt_array($ch, [CURLOPT_URL => 'http://'.$tuner.'/status.json', CURLOPT_TIMEOUT => 1, CURLOPT_RETURNTRANSFER => 1]);
          curl_exec($ch);
          if(curl_getinfo($ch, CURLINFO_HTTP_CODE) == 200){
            $online[] = $tuner;
          }
      }
      curl_close($ch);
      return $online;
  }
  
  function getTunerUsage($tunerIP){
        $json = http_get_contents('http://'.$tunerIP.'/status.json');
        $channels = [];
        foreach(json_decode($json,true) as $tuner){
            if(isset($tuner['VctNumber']) && isset($tuner['VctName'])){
                $channels[] = array('number'=>$tuner['VctNumber'],'name'=>$tuner['VctName']);
            }
        }
        return $channels;
  }
  
  function getChannelsWatching(){
      $tunersOnline = getTunersOnline();
      $channels = [];
      foreach($tunersOnline as $tunerOnline){
        foreach(getTunerUsage($tunerOnline) as $tuned){          
            $channels[] = $tuned['number'];
        }
      }
      return $channels;
  }
  
  function printTunersWatching(){
      $tunersOnline = getTunersOnline();
      $output = 'Tuners online: '.implode(', ',$tunersOnline).'<br>';
      
      foreach($tunersOnline as $tunerOnline){
          $output .= 'Tuner '.$tunerOnline.' watching: [';
          $tuned = getTunerUsage($tunerOnline);
          foreach($tuned as $key => $details){
              $output .= $details['number'].' ('.$details['name'].'), ';
          }
          $output .= ']<br>';
      } 
      return $output;
  }
  
  function getChannelsTuned(){
    $usage = array();
    foreach(getTunersOnline() as $tunerIP){    
      foreach(getTunerUsage($tunerIP) as $channel){
          $usage[] = $channel;
      }    
    }
    return $usage;
  }
  
  function getTunerWithFreeSlot(){
      $tuners = getTunersOnline();
      foreach($tuners as $tuner){
          $channels = getTunerUsage($tuner);
          if(count($channels) < 4){
              return $tuner;
          }
      }
      return false;
  }
  
  function getTunerListPerSlot(){
      $list = array();
      $tuners = getTunersOnline();
      foreach($tuners as $tuner){
          $channels = getTunerUsage($tuner);
          for($i=0; $i < 4-count($channels); $i++){
              $list[] = $tuner;
          }
      }
      return $list;
  }
  
  function getTunerChannelLineUp($tunerIP){ 
      $json = http_get_contents('http://'.$tunerIP.'/lineup.json?show=found');
      $channelLineup = json_decode($json,true);
      return $channelLineup;
  }
  
  function isChannelInLineUp($tunerIP, $channel){
      foreach(getTunerChannelLineUp($tunerIP) as $lineupChannel){
          if($lineupChannel['GuideNumber'] == $channel){
              return true;
          }
      }
      return false;
  }
  
  function whatsOnNow(){
      $whatsOn = array();
      $channelLineup = getChannelLineup(); // channels tunable on tuner
      foreach($channelLineup as $station){
        $channel = $station['GuideNumber'];
        $station = $station['GuideName'];
        
        $xml = fetchEpgXml();
        $zapChannel = $xml->xpath('/tv/channel/display-name[text()="'.$channel.'"]/..');
        if($zapChannel == null){
          $whatsOn[$channel] = '(Channel not found in epg guide.)';
          continue;
        }
        $zapChannel = $zapChannel[0]['id'];
        $shows = $xml->xpath('/tv/programme[@channel="'.$zapChannel.'"]');
        
        foreach($shows as $show){  
            if(time() > strtotime($show['start']) && time() < strtotime($show['stop'])){
                $whatsOn[$channel] = (string)$show->title;
                continue 2;
            }
        }
      }
      return $whatsOn;
  }
  
  function printWhatsOnNow(){
      $whatsOn = whatsOnNow();
      $channels = getChannelsWatching();
      $output = print_r($whatsOn,true);
      $lines = explode("\n", $output);
      foreach($lines as $key => $line){
          foreach($channels as $channel){
              if(strstr($line, '['.$channel.']')){
                  $lines[$key] = '<font style="background: lightyellow; font-weight: bold;">'.$line.' (Recording)</font>';
                  continue 2;
              }
          }
      }
      $lines = implode("\n", $lines);
      echo '<pre>'.$lines.'</pre>';
  }
  
  function whatsOnAllTunerChannels(){
      findAllShowsInEPG();
  }
  
  function whatsOnPickedChannels(){
      foreach(getChannelsToRecord() as $channel){
          echo 'Channel: '.$channel.'<br>';
          findAllShowsInEPG($channel);
          echo '<hr>';
      }
  }
  
  function findAllShowsInEPG(){
      $xml = fetchEpgXml();
      $channelLineup = getChannelLineup(); // channels on tuner
      $epgShows = array();
      foreach($channelLineup as $lineupItem){
        $channel = $lineupItem['GuideNumber'];
        $station = $lineupItem['GuideName'];
      
        $zapChannel = $xml->xpath('/tv/channel/display-name[text()="'.$channel.'"]/..');
        if($zapChannel == null){
          $epgShows[$channel] = 'channel not found in epg guide.';
          continue;
        }
        $zapChannel = $zapChannel[0]['id'];
        $shows = $xml->xpath('/tv/programme[@channel="'.$zapChannel.'"]');
        
        foreach($shows as $show){
          if(time() > strtotime($show['start']))
            $epgShows[$channel][] = (string)$show->title;
        }
      
        $epgShows[$channel] = array_values(array_unique($epgShows[$channel]));        
      }
      logw('<pre>'.print_r($epgShows,true).'</pre>');
  }
  
  function buildShowMetaDataForVideoFile($channel, $show){
    $metadata = array();
    $metadata['title'] = (string)$show->title;
    $metadata['show'] = (isset($show->{'sub-title'}))?(string)$show->{'sub-title'}:'';
    $metadata['episode_id'] = (isset($show->{'episode-num'}[0]))?(string)$show->{'episode-num'}[0]:'';
    $metadata['description'] = (string)$show->{'desc'};
    $metadata['network'] = getNetwork($channel);
    $metadata['comment'] = 'Recorded '.getTimeStamp();
    return $metadata;
  }
  
  function detectChannels($tunerIP){
    $ch = curl_init('http://'.$tunerIP.'/lineup.post?scan=start&source=Antenna');
    curl_setopt_array($ch, [CURLOPT_TIMEOUT => 1, CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true]);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
  }
  
  function detectChannelsAllTuners(){
    foreach(getTunersOnline() as $tuner){
      detectChannels($tuner);
    }
  }
   
  function getChannelNameFromChannelNumber($tunerIP, $channelNumber){
      foreach(getTunerChannelLineUp($tunerIP) as $item){
          if($item['GuideNumber'] == $channelNumber){
              return $item['GuideName'];
          }
      }
      $xml = fetchEpgXml();
      $zapChannel = $xml->xpath('/tv/channel/display-name[text()="'.$channelNumber.'"]/..')[0]->xpath('display-name')[2];
      logw('<pre>'.print_r($zapChannel,true).'</pre>');
      return (string)$zapChannel;
  }
  
  function getNetwork($channel){
      $overrides = ['9.1' => 'My9', '63.2' => 'GetTV', '68.3' => 'QUEST'];
      
      if(in_array($channel, array_keys($overrides))){
          $network = $overrides[$channel];
      }
      else{
          $network = str_replace(' ', '', getChannelNameFromChannelNumber(getTunersOnline()[0], $channel));
      }
      return '['.$network.'][v'.$channel.']';
  }
  
  function findCurrentShow($channel){     
      $xml = fetchEpgXml();
      $zapChannel = $xml->xpath('/tv/channel/display-name[text()="'.$channel.'"]/..');
      if($zapChannel == null){
          logw('channel not found in epg guide.');
          return;
      }
      logw('<pre>Channel: '.print_r($zapChannel,true).'</pre>');
      $zapChannel = $zapChannel[0]['id'];

      $shows = $xml->xpath('/tv/programme[@channel="'.$zapChannel.'"]');
      //echo '<pre>'.print_r($shows,true).'</pre>';
      
      foreach($shows as $show){
        $datetime = (new DateTime('now', new DateTimezone('America/New_York')))->format("YmdHis O");
        if(strtotime($datetime) >= strtotime($show['start'])-7 && strtotime($datetime) < strtotime($show['stop'])){    
            logw('<pre>Show: '.print_r($show,true).'</pre>');
            return $show;
        }
      }
      return false; 
  }
  
  function getTimeStamp(){
    $timezone = 'America/New_York';
    return (new DateTime('now', new DateTimezone($timezone)))->format('Y-m-d g:i:sA').$timezone;
  }
  
  function isRecording($channel){
    foreach(getChannelsTuned() as $tunedChannel){
        if($channel == $tunedChannel['number']){
            return true;
        }
    }
    return false;
  }
  
  function filter_filename($name){
    // remove illegal file system characters https://en.wikipedia.org/wiki/Filename#Reserved_characters_and_words
    $name = str_replace(array_merge(
        array_map('chr', range(0, 31)),
        array('<', '>', ':', '"', '/', '\\', '|', '?', '*')
    ), '', $name);
    // maximise filename length to 255 bytes http://serverfault.com/a/9548/44086
    $ext = pathinfo($name, PATHINFO_EXTENSION);
    $name = mb_strcut(pathinfo($name, PATHINFO_FILENAME), 0, 255 - ($ext ? strlen($ext) + 1 : 0), mb_detect_encoding($name)) . ($ext ? '.' . $ext : '');
    return $name;
  }
  
  function buildFilePath($channel, $show){
    $path = 'D:/tv/incomplete/';
    $filename = '';
    $network = getNetwork($channel);
      
    if($show == false){
      echo 'show not found in epg<br>';
      $filename = (new DateTime('now', new DateTimezone('America/New_York')))->format('Ymd-His');
    }
    else {
        if(isset($show->category) && (string)$show->category == 'Movie'){
            $path .= 'Movies/';
            if(!file_exists($path)){
                mkdir($path);
            }
            $filename .= '[Movie]-'.trim((string)$show->title).' '.(string)$show->date.' - ';
        }
        else{
            $path .= filter_filename(trim((string)$show->title))./*$network.*/'/';
            $filename .= trim((string)$show->title);
            
            if(isset($show->{'episode-num'}[0])){
                $filename .= ' - '.trim((string)$show->{'episode-num'}[0]).' - ';
            }
        
            if(isset($show->{'sub-title'})){
                $filename .= trim((string)$show->{'sub-title'}).' - ';
            }
        }
    }
    $filename .= $network.'.mp4';    
    return $path.filter_filename($filename);
  }
  
  function skipShows(){
      return explode("\r\n", file_get_contents('D:/tv/settings/skipshow.list'));
  }
  
  function errorRepeatLoop($error, $duration=5){
      logw($error);
      cli_set_process_title('Error: '.$error);
      
      $startTime = time();
      if($duration == -1){
          while(true){}
      }
      else{
          while($startTime+$duration > time()){
              sleep(1);
          }
      }
      logw('exiting...');
      exit();
  }
  
  function record($channel, $tuner=null, $loop=true){
      do{
          transferLogFile(date('Y-m-d').'['.$channel.'].html');
          logw('<hr>record function called, channel: '.$channel.', tuner: '.$tuner.', loop: '.$loop);
          //logw('PeakPageFileUsage in bytes: '.getMemoryUsage());
          
          if($tuner == null){
            $tuner = getTunerWithFreeSlot();
          }
          if($tuner == false){ // delay?
            errorRepeatLoop('no free tuner slots available');
          }
          else if(isRecording($channel)){ // ffmpeg connected to tuner.  what about skipping?
            errorRepeatLoop('already recording channel '.$channel);
          }
          else if(!isChannelInLineUp($tuner, $channel)){ // try another open tuner?
            errorRepeatLoop('channel not in lineup of tuner '.$tuner);
          }
          else if(filemtime('D:/tv/programs/atsc-guide.xml') + 2*24*60*60 < time()){
            errorRepeatLoop('epg is too old.'); // download new epg?  find min/max last show time for channels
          }
          $show = findCurrentShow($channel);
          if($show == false){
            errorRepeatLoop('failed to find current show on channel.');
          }
          logw('show ends at time: '.date('h:i:s A', strtotime($show['stop'])));
          if(in_array(filter_filename((string)$show->title), skipShows())){
            logw('skipping show, found in skip list: '.(string)$show->title);
            if($loop == false){ exit; }
            skipShow($channel, $show);
            sleep(1);
            continue;
          }
      
          $elapsed = time()-strtotime($show['start']);
          $maxLateStart = 60;
          if($elapsed > $maxLateStart){
            logw('skipping recording, started too late: '.$elapsed.' > '.$maxLateStart);
            if($loop == false){ exit; }
            skipShow($channel, $show);
            sleep(1);
            continue;
          }
          
          $duration = strtotime($show['stop'])-time();
          //echo 'Duration: '.$duration;
          if($duration < 5*60){
            logw('skipping recording, only 5 minutes left');
            if($loop == false){ exit; }
            skipShow($channel, $show);
            sleep(1);
            continue;
          }
        
          $destination = buildFilePath($channel, $show);
          logw('Destination: '.$destination);
          $dir = pathinfo($destination, PATHINFO_DIRNAME).'/';
          if(file_exists($dir) == false || is_dir($dir) == false){
            mkdir($dir, 0777, $recursive=true);
          }
          //logw('PeakPageFileUsage in bytes: '.getMemoryUsage());
          ffmpeg($tuner, $channel, $show, $duration, $destination);
          //logw('PeakPageFileUsage in bytes: '.getMemoryUsage());
          for($i=0; $i<3; $i++){
            if(file_exists($destination)){
                break;
            }
            else{
                sleep(1);
            }
          } 
      
          if(file_exists($destination)){
            logw('file exists in '.$destination);
            $filesize = filesize($destination);
            $newPath = str_replace('incomplete/','complete/', $destination);
            
            $minMB = 50;
            if($filesize < $minMB){
                logw('warning: file size ('.number_format($filesize/1024/1024,2).'MB) is less than '.$minMB.'MB');
                unlink($destination);
                continue;
            }
            else if(strtotime((string)$show['stop'])-time() > 60){
                unlink($destination);
                logw('recording ended too early.  deleted the partial file.');
                if($loop == true){
                    continue;
                }
            }
            else if(file_exists($newPath) && filesize($newPath) < filesize($destination)){ // overwrite if new file is larger
                unlink($newPath);
                logw('deleting previous file because it\'s smaller and has the same name.');
            }
            $dir = pathinfo($newPath, PATHINFO_DIRNAME).'/';
            if(file_exists($dir) == false || is_dir($dir) == false){            
                if(mkdir($dir, 0777, $recursive=true)){
                    logw('made directory '.$dir);
                };
            }
            logw('moving file from ['.$destination.'] to ['.$newPath.']');
            if(rename($destination, $newPath)){
                logw('file moved from /incomplete/ folder to /complete/ folder');                
                $folder = pathinfo($destination, PATHINFO_DIRNAME);
                logw('incomplete folder: '.$folder);
          
                /*if(file_exists($folder) && is_dir($folder)){
                    array_map('unlink', array_filter((array) glob($folder.'/*')));
                    $fileCount = count(array_diff(scandir($folder),array('.','..')));
                    logw('files in folder: '.$fileCount);
                
                    if(rmdir($folder)){
                        logw('deleted empty folder in /incomplete/ '.$folder);
                    }
                }*/
                print("\r\n");
                logw('about to record the next program.<hr>');            
            }
            else{
                logw('failed to move file to /complete/ folder');
            }
          } 
          else{
            logw('destination file does not exist: '.$destination);
          }
          //logw('PeakPageFileUsage: '.getMemoryUsage());
      }
      while($loop == true);
  }
  
  function recordingStatusLine($mode='Skipping', $channel, $show, $metadata, $filesize='0.0'){
      $stopTime = strtotime((string)$show['stop']);
      $line = $mode.': '.$metadata['network'];
      $line .= ((strlen($metadata['title']) > 20)?substr($metadata['title'],0,20).'...':$metadata['title']);
      $line .= ' - '.substr($metadata['episode_id'],0,6);
      $line .= '['.$filesize.'MB]';      
      $line .= '['.max($stopTime-time(),0).'s]';
      $line .= '['.number_format(min((time()-strtotime($show['start']))/(strtotime($show['stop'])-strtotime($show['start'])),1)*100,1).'%]';
      return $line;
  }
  
  function skipShow($channel, $show){
    global $log;
    $log['path'] = pathinfo($log['path'], PATHINFO_DIRNAME).'/'.date('Y-m-d').'['.$channel.'].html';
    if($show != null){
        $metadata = buildShowMetaDataForVideoFile($channel, $show);
        $stopTime = strtotime((string)$show['stop']);
        while (time() < $stopTime){
            $statusLine = recordingStatusLine('Skipping', $channel, $show, $metadata, $filesize='0.0');
            if(time() % 60 == 0){
                logw($statusLine, $silent=true);
                $statusLine .= "\033[2K\r";
            }
            
            print $statusLine ."\r";
            cli_set_process_title($statusLine);
            sleep(1);                          
        }
        print "\r\n";
    }
  }
  
  // newest ffmpeg auto-build: https://github.com/BtbN/FFmpeg-Builds/releases/download/latest/ffmpeg-master-latest-win64-gpl.zip
  function ffmpeg($tuner, $channel, $show, $duration, $destination){ // listen to ffmpeg to detect http 5xx error (failed to connect to station)
    $metadata = buildShowMetaDataForVideoFile($channel, $show);
    $cmd = array();
    $cmd[] = 'D:/tv/programs/ffmpeg.exe';
    
    if($show != null){
        $stopTime = strtotime((string)$show['stop']);
        $cmd[] = '-t '.($stopTime-time());
    }
    $cmd[] = '-hide_banner -y';
    $cmd[] = '-i http://'.$tuner.':5004/auto/v'.$channel;//.'?duration='.$duration;
    if($metadata != false){
        foreach($metadata as $key => $val){
            $cmd[] = '-metadata '.$key.'='.escapeshellarg($val);
        }
    }
    //$cmd[] = '-c copy '.escapeshellarg($destination).' 2>&1';
    $cmd[] = '-c copy "'.$destination.'" 2>&1';
    
    $cmd = implode(' ',$cmd);
    
    logw('Command: '.$cmd);
    
    $logFile = $destination;
    $logFile = 'D:/tv/logs/'.pathinfo($logFile, PATHINFO_BASENAME);
    $logFile = str_replace('.mp4', '.html', $logFile);
    $folder = pathinfo($logFile, PATHINFO_DIRNAME);
    if(!file_exists($folder)){
        mkdir($folder, 0777, $recursive=true);
    }
    $fp = fopen($logFile, 'a');

    $descriptorspec = array(
        0 => array('pipe', 'r'),   // stdin is a pipe that the child will read from
        1 => array('pipe', 'w'),   // stdout is a pipe that the child will write to
        2 => array('pipe', 'w')    // stderr is a pipe that the child will write to
    );
    
    //ob_implicit_flush(1);
    $process = proc_open($cmd, $descriptorspec, $pipes, $cwd=null, $env_vars=null, $options=array('bypass_shell'=>false));
    //echo '<hr><pre>';
    fwrite($fp, '<hr><pre>');
    $line = '';
    if (is_resource($process)){
        print "";
        while (!feof($pipes[1])){
            if(time() >= $stopTime + 30){ // should self-terminate before this
                fwrite($pipes[0], 'q');
                logw('sent q key to signal quit');
                while(is_resource($process) && proc_get_status($process)['running'] == 1){
                    sleep(1);
                    logw('waiting for process to end cleanly');
                }
                break;
            }
            
            $s = fread($pipes[1],1);
            $line .= $s;
            if($s == "\r"){                
                if(substr($line, 0, 5) == 'size='){
                    $end = strpos($line, 'KiB');
                    $size = number_format(trim(substr($line, 5, $end-5))/1024,1);
                    $statusLine = recordingStatusLine('Ripping', $channel, $show, $metadata, $filesize=$size);
                    if(time() % 60 == 0){
                        logw($statusLine, $silent=true);
                        $statusLine .= "\033[2K\r";
                    }
                    print $statusLine ."\r";
                    cli_set_process_title($statusLine);
                    flush();
                }
                else if(strstr($line, 'HTTP error')){
                    logw('HTTP error detected: '.$line);
                }
                $line = '';                        
            }
            fputs($fp, $s);
            flush();
        }
    }
    if(is_resource($process) && proc_get_status($process)['running'] == 1){
        proc_close($process);
        sleep(1);
        if(is_resource($process) && proc_get_status($process)['running'] == 1){
            proc_terminate($process);
            sleep(1);
        }
    }
    fwrite($fp, '</pre>');
    //echo '</pre>';
    fclose($fp);
    logw('<pre>'.print_r(proc_get_status($process),true).'</pre>');
    logw('end of ffmpeg()');
  }
  
  function handleVideoErrors(){
    exec('D:/tv/programs/Handle/handle64.exe -accepteula "Y:/tv/incomplete/Archie Bunker\'s Place/Archie Bunker\'s Place - S03E26 - Death of a Lodger - [ANTENNA][v11.2].mp4"', $output, $return);
    print_r($output);
    echo number_format(microtime(true)-$start). ' seconds';
    exit;
    $log['path'] = 'D:/tv/logs/videos_error_deletion.html';
    
    $folder = 'D:/tv/complete/ES.TV/';
    $files = scandir($folder);
    $files = array_values(array_diff($files,array('.','..')));
    //print_r($files);
    logw('<hr>Starting video file check/delete function');
    foreach($files as $file){
        echo filemtime($folder.$file);
        $output = array();
        $cmd = 'D:/tv/programs/ffmpeg.exe -v error -i "'.$folder.$file.'" -map 0:1 -f null - 2>&1';
        logw('command: '.$cmd);
        exec($cmd, $output, $return);
        if(!empty($output)){
            logw('<pre>Output:'.print_r($output,true).'</pre>');
        }
        $stdouterr = implode("\n", $output);
        logw('return code: '.$return);
        if(stristr($stdouterr, 'Invalid data found when processing input') || (substr_count($stdouterr,'Error') + substr_count($stdouterr,'error')) > 20){
            logw('contains video/audio stream errors: '.$file);
            if(unlink($folder.$file)){
                logw('deleted file');
                if(count(array_diff(scandir($folder),array('.','..'))) == 0){
                    rmdir($folder);
                    logw('deleted empty folder');
                }
            }
        }
        else{
            logw('did not detect video/audio stream errors: '.$file);
            logw('keeping file');
        }
        //print_r($output);
    }      
  }
  
  function getChannelsToRecord(){
      $path = "D:/tv/settings/channels.json";
      $channels = json_decode(file_get_contents($path),true);
      if(json_last_error() != 0){
        trigger_error('JSON Error: '.json_last_error_msg().' in file', E_USER_ERROR);
        return;
      }
      if(count($channels) == 0){
          echo 'No channels found in channels.json';
          return;
      }
      return $channels;
  }
  
  function getProcesses($program='php'){
      $programList = '';
      if($program == 'php'){
        $programList .= 'wmic path win32_process where "caption like \'%php%\' and commandline like \'%functions.php%\' and commandline like \'%record%\' and (';
        foreach(getTunersOnline() as $onlineTuner){
          $programList .= 'commandline like \'%'.$onlineTuner.'%\' or ';
        }
        $programList = substr($programList, 0, -4);
        $programList .= ')" get caption, processid, parentprocessid, commandline /format:list'; // parentprocessid
      }
      else if($program == 'ffmpeg'){
        $programList .= 'wmic path win32_process where "caption like \'%ffmpeg%\' and (';
        foreach(getTunersOnline() as $onlineTuner){
          $programList .= 'commandline like \'%'.$onlineTuner.':5004%\' or ';
        }
        $programList = substr($programList, 0, -4);
        $programList .= ')" get caption, processid, parentprocessid, commandline /format:list'; // parentprocessid
      }
      
      //echo $programList.'<br>';
      //exit();
      
      $output = array();
      exec($programList, $output, $return);
      //echo '<pre>'.print_r($output, true).'</pre>';
      $procs = [];
      for($i=2; $i<count($output)-3; $i+=6){
          $procs[] = array(
            'Caption'=>substr($output[$i], strlen('Caption=')),
            'CommandLine'=>substr($output[$i+1], strlen('CommandLine=')),
            'ParentProcessId'=>substr($output[$i+2], strlen('ParentProcessId=')),
            'ProcessId'=>substr($output[$i+3], strlen('ProcessId='))
          );
      }
      //logw('<pre>'.print_r($procs, true).'</pre>');
      return $procs;
  }
  
  function closeAllRecordings(){
      $procs = getProcesses();
      $command = 'taskkill ';
      foreach($procs as $proc){    
          $command .= '/PID '.$proc['ProcessId'].' ';
          //$command .= '/PID '.$proc['ParentProcessId'].' ';
      }
      $command .= '/F /T';
      $output = array();
      exec($command, $output, $return);
      echo '<pre>'.print_r($output, true).'</pre>';
  }
  
  function clearData(){/*
      $deletedCount = 0;
      $folders = array('D:/tv/incomplete/','D:/tv/logs/');
      foreach($folders as $folder){
          $files = array_values(array_diff(scandir($folder), array('.','..')));
          echo '<pre>'.print_r($files,true).'</pre>';
          foreach($files as $file){
              if(is_file($folder.$file) && in_array(pathinfo($folder.$file, PATHINFO_EXTENSION), array('mkv','html'))){
                  if(unlink($folder.$file)){
                      $deletedCount++;
                  }
              }
          }
      }
      echo 'Deleted files: '.$deletedCount.'<br>';*/
  }
  
  function isFileInUse($path=null){
    //$path = 'Y:/tv/incomplete/The Best of the Joy of Painting/The Best of the Joy of Painting - S40E23 - Daisy Delight - [CREATE][v21.2].mp4';
    $output = array();
    exec('D:/tv/programs/Handle/handle64.exe -nobanner -accepteula -v '.escapeshellarg($path), $output, $return);
    //echo '<pre>'.print_r($output, true).'<pre>';
    if(count($output) > 1){
        $line = str_getcsv($output[1]);
        echo '<pre>'.print_r($line, true).'<pre>';
        //echo 'file is in use';
        return true;
    }
  }
  
  function deleteGlitchyIncompleteVideos(){
      $base = 'D:/tv/incomplete/';
      $folders = array_values(array_diff(scandir($base), array('.','..')));
      foreach($folders as $folder){
          if(is_dir($base.$folder)){
              $files = array_values(array_diff(scandir($base.$folder), array('.','..')));
              foreach($files as $file){
                  $path = $base.$folder.'/'.$file;
                  logw('checking video file: "'.$path.'"');
                  if(isFileInUse($path)){
                      logw('file in use, leaving it alone.');
                  }
                  else if(checkVideoFile($path) == false){
                      logw('video file has errors');
                      logw('deleted file: '.(unlink($path)?'yes':'no'));
                  }
                  else{
                      logw('video is good and not in use.');
                      $newPath = str_replace('D:/tv/incomplete/','D:/tv/complete/', $path);
                      $newParentFolder = pathinfo($newPath, PATHINFO_DIRNAME);
                      if(!file_exists($newParentFolder)){
                          mkdir($newParentFolder);
                      }
                      $mTime = filemtime($newParentFolder);
                      logw('moved to /complete/: '.rename($path,$newPath));
                      touch($newParentFolder, $mTime);
                  }
              }
          }
      }
      deleteEmptySubFolders();
  }
  
  function checkVideoFile($path=null){
      $output = array();
      $cmd = 'D:/tv/programs/ffmpeg.exe -v error -i '.escapeshellarg($path).' -map 0:1 -f null - 2>&1';
      exec($cmd, $output, $return);
      foreach($output as $line){
          if(strstr($line, 'moov atom not found')){
              return false;
          }
      }
      return true;
  }
  
  function deleteEmptySubFolders(){
      transferLogFile('deletions_'.date('Y-m-d').'.html');
      foreach(array('incomplete','complete') as $baseFolder){
           $base = 'D:/tv/'.$baseFolder.'/';
           logw('deleting empty folders');
           $folders = array_values(array_diff(scandir($base), array('.','..')));
           foreach($folders as $folder){
                $innerFolder = array_values(array_diff(scandir($base.$folder), array('.','..')));
                if(count($innerFolder) == 0){
                    logw('deleting empty folder, '.$base.$folder.': '.(rmdir($base.$folder)?'success':'failed'));
                }
           }     
      }
  }
  
  function hdhomerun_config($args=''){
      $program = 'D:/tv/programs/HDHomeRun/hdhomerun_config.exe';
      $output = array();
      if(is_array($args)){
          $args = implode(' ', $args);
      }
      exec($program.' '.$args, $output, $return);
      echo '<pre>'.print_r($output, true).'</pre>';
  }
  
  function freeSpacePercent($directory){
    return number_format(100*disk_free_space($directory) / disk_total_space($directory),0);
  }
  
  function deleteOldestVideos(){
      // https://www.php.net/manual/en/function.touch.php
      // to leave the folder modified times as they were
      transferLogFile('deletions_'.date('Y-m-d').'.html');
      $minFreeSpacePercent = 20;
      $base = 'D:/tv/complete/';
      
      $videoFiles = array();
      foreach(array_diff(scandir($base), array('.','..')) as $show){
          foreach(array_diff(scandir($base.$show), array('.','..')) as $video){
              $videoFiles[$base.$show.'/'.$video] = filemtime($base.$show.'/'.$video);
          }
      }
      asort($videoFiles);
      logw('total video files: '.count($videoFiles));
      
      $deletedFiles = array();
      if(freeSpacePercent('D:') < $minFreeSpacePercent){
        logw('free space is now '.freeSpacePercent('D:').'% and min free space percent must be '.$minFreeSpacePercent.'% or more');
          foreach(array_keys($videoFiles) as $videoFilePath){
              if(freeSpacePercent('D:') < $minFreeSpacePercent){
                if(file_exists($videoFilePath)){
                  $parentFolder = pathinfo($videoFilePath, PATHINFO_DIRNAME);
                  $mTime = filemtime($parentFolder);
                  unlink($videoFilePath);
                  touch($parentFolder, $mTime);
                  if(!file_exists($videoFilePath)){
                      array_push($deletedFiles, $videoFilePath);
                      logw('deleted file: '.$videoFilePath);
                  }
                }
                logw('free space is now '.freeSpacePercent('D:').'% and min free space percent must be '.$minFreeSpacePercent.'% or more');
              }
              else{
                  break;
              }
          }
      }
      logw('files deleted: '.count($deletedFiles));
      
  }
  
      /*$delay = 99999;
    do{
        $min = date('i');
        $sec = date('s');
        
        if($min > 30){
            $delay = (60 - $min)*60+(60-$sec);
        }
        else if($min <= 30){
            $delay = (30 - $min)*60+(60-$sec);
        }
        logw('sleeping for '.$delay.' seconds<br>');
        if($delay > 10){
            sleep(1);
        }
        
    }while($delay > 10);*/
  
?>

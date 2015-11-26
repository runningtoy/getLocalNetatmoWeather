<?php
error_reporting(E_ALL ^ E_NOTICE);

//Abfrage der NetATMO Public Sensoren um die lokale Ist-Temperatur zu ermitteln

$searchadress=$_GET['address'];   //Mindestparameter
$distance= (isset($_GET['distance'])) ? $_GET['distance'] : 1; //Distancparameter ist optional in KM
$valuetype= (isset($_GET['value'])) ? $_GET['value'] : "temperature"; //Welcher Sensortyp soll abgefragt werden
$debug= (isset($_GET['debug'])) ? $_GET['debug'] : false; //Welcher Sensortyp soll abgefragt werden
 
$searchadress=str_replace(' ', '+',$searchadress);

//google API URL um Koordinaten zu ermitteln
$api_url = "http://maps.google.com/maps/api/geocode/json?address=" . $searchadress;
if($debug){echo $api_url . "<br>";}

//http call
$result = file_get_contents($api_url);  
$json_devices = json_decode($result);

//Koordinaten abfragen
$lat=($json_devices->results[0]->geometry->location->lat);
$lng=($json_devices->results[0]->geometry->location->lng);

list($lat_ne,$lon_ne) = getDueCoords($lat, $lng, 45, $distance);
list($lat_sw,$lon_sw) = getDueCoords($lat, $lng, 225, $distance);

if($debug){echo "Lat:"  .$lat . "<br>";}
if($debug){echo "Lon:"  .$lng . "<br>";}
if($debug){echo "Lat_SW:"  .$lat_sw . "<br>";}
if($debug){echo "Lon_SW:"  .$lon_sw . "<br>";}
if($debug){echo "Lat_NE:"  .$lat_ne . "<br>";}
if($debug){echo "Lon_NE:"  .$lon_ne . "<br>";}

//Netatmo Settings
$username="";
$password="";
$app_id = "";
$app_secret = "";


//Netatmo Token ermitteln
$token_url = "https://api.netatmo.net/oauth2/token";
$postdata = http_build_query(
        array(
            'grant_type' => "password",
            'client_id' => $app_id,
            'client_secret' => $app_secret,
            'username' => $username,
            'password' => $password
    )
);

$opts = array('http' =>
    array(
        'method'  => 'POST',
        'header'  => 'Content-type: application/x-www-form-urlencoded',
        'content' => $postdata
    )
);

$context  = stream_context_create($opts);
$response = file_get_contents($token_url, false, $context);
$params = null;
$params = json_decode($response, true);



//API Url zusammenbauen und JSON Objekt ermittlen
$api_url = "https://api.netatmo.net/api/getpublicdata?access_token=" .  $params['access_token'] . "&lat_ne=" . $lat_ne ."&lon_ne=" . $lon_ne ."&lat_sw=" . $lat_sw ."&lon_sw=" . $lon_sw ."&filter=true";
$localweatherstations = file_get_contents($api_url);     //url abfragen
$json_devices = json_decode($localweatherstations); //JSON generieren


//Werte ermitteln
echo getWeatherValue($json_devices,$valuetype,$debug); //Durchschnittswert ermitteln




function getWeatherValue($json, $name,$debug){
   $dev_count=0;
   $temp_sum=0;
   foreach($json->body as $key => $devices)
   { //loop ?er einzelne Messstellen
      foreach ($devices->measures as $key => $measures)
      { //einzelnen Messger?e
         if(isset($measures->type))
         {   //hat das Messger? ?erhaupt Sensoren
            $count=count($measures->type);
            for ($x = 0; $x < $count; $x++)
            { //loop ?er alle sensoren
              if ($measures->type[$x] == $name)
              {   //gibt es einen Temperatursensor?
                foreach ($measures->res as $k=>$v)
                {   //wenn es einen Sensor gibt loop ?er alle "res" (gibt nur eins)
                   //echo json_encode(($v[$x]), JSON_PRETTY_PRINT) . "<br>########<br>";
                  $temp_sum=$temp_sum+$v[$x];   //temperatur addieren
                  $dev_count++;  //Gefunden Messsensoren
                  if($debug){echo $v[$x] . "<br>";}
                }
              }
            }
         }
      }
   }
   if($debug){echo "Anzahl an Messstationen " . $dev_count . "<br>";}
   if($debug){echo "Durchschnittswert " . $temp_sum/$dev_count . "<br>";}
   return $temp_sum/$dev_count;
}

function getDueCoords($latitude, $longitude, $bearing, $distance, $distance_unit = "km") {
   if ($distance_unit == "m") {
   // Distance is in miles.
     $radius = 3963.1676;
   }
   else {
   // distance is in km.
   $radius = 6378.1;
   }

   //	New latitude in degrees.
   $new_latitude = rad2deg(asin(sin(deg2rad($latitude)) * cos($distance / $radius) + cos(deg2rad($latitude)) * sin($distance / $radius) * cos(deg2rad($bearing))));
      
   //	New longitude in degrees.
   $new_longitude = rad2deg(deg2rad($longitude) + atan2(sin(deg2rad($bearing)) * sin($distance / $radius) * cos(deg2rad($latitude)), cos($distance / $radius) - sin(deg2rad($latitude)) * sin(deg2rad($new_latitude))));

   return array($new_latitude,$new_longitude);
  
}	
?>
 



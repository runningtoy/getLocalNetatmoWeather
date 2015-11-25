<?php
error_reporting(E_ALL ^ E_NOTICE);

//Abfrage der NetATMO Public Sensoren um die lokale Ist-Temperatur zu ermitteln
//Da kann man php code laufen lassen
//http://phpfiddle.org/
//Info er Errors
//http://php.net/manual/de/function.error-reporting.php
//hier hab ich die Boundingbox her
//https://www.sitepoint.com/community/t/adding-distance-to-gps-coordinates-to-get-bounding-box/5820/10


$searchadress=$_GET['address'];   //Mindestparameter
$distance= (isset($_GET['distance'])) ? $_GET['distance'] : 1; //Distancparameter ist optional in KM
$valuetype= (isset($_GET['value'])) ? $_GET['value'] : "temperature"; //Welcher Sensortyp soll abgefragt werden
$debug= (isset($_GET['debug'])) ? $_GET['debug'] : false; //Welcher Sensortyp soll abgefragt werden


 
$searchadress=str_replace(' ', '+',$searchadress);

//google API URL um Koordinaten zu ermitteln
$api_url = "http://maps.google.com/maps/api/geocode/json?address=" . $searchadress;
if($debug){echo $api_url . "<br>";}

$result = file_get_contents($api_url);     

$json_devices = json_decode($result);

//Koordinaten abfragen
$lat=($json_devices->results[0]->geometry->location->lat);
$lon=($json_devices->results[0]->geometry->location->lng);

//Eckkoordinaten berechnen f NETATMO
list($lat_sw,$lon_sw,$lat_ne,$lon_ne) = getBoundingBox($lat, $lon, $distance);

if($debug){echo "Lat:"  .$lat . "<br>";}
if($debug){echo "Lon:"  .$lon . "<br>";}
if($debug){echo "Lat_SW:"  .$lat_sw . "<br>";}
if($debug){echo "Lon_SW:"  .$lon_sw . "<br>";}
if($debug){echo "Lat_NE:"  .$lat_ne . "<br>";}
if($debug){echo "Lon_NE:"  .$lon_ne . "<br>";}

$username="";
$password="";
$app_id = "";
$app_secret = "";

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

//API Url zusammenbauen
$api_url = "https://api.netatmo.net/api/getpublicdata?access_token=" .  $params['access_token'] . "&lat_ne=" . $lat_ne ."&lon_ne=" . $lon_ne ."&lat_sw=" . $lat_sw ."&lon_sw=" . $lon_sw ."&filter=true";
$localweatherstations = file_get_contents($api_url);     //url abfragen

$json_devices = json_decode($localweatherstations); //JSON generieren

echo getWeatherValue($json_devices,$valuetype,$debug); //Durchschnittswert ermitteln




function getWeatherValue($json, $name,$debug){
$dev_count=0;
$temp_sum=0;
foreach($json->body as $key => $devices)
{ //loop er einzelne Messstellen
   foreach ($devices->measures as $key => $measures)
   { //einzelnen Messger舩e
      if(isset($measures->type))
      {   //hat das Messger舩 erhaupt Sensoren
         $count=count($measures->type);
         for ($x = 0; $x < $count; $x++)
         { //loop er alle sensoren
           if ($measures->type[$x] == $name)
           {   //gibt es einen Temperatursensor?
             foreach ($measures->res as $k=>$v)
             {   //wenn es einen Sensor gibt loop er alle "res" (gibt nur eins)
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

//return floor(($temp_sum/$dev_count) * 2) / 2; //runden der Werte

if($debug){echo "Anzahl an Messstationen " . $dev_count . "<br>";}
if($debug){echo "Durchschnittswert " . $temp_sum/$dev_count . "<br>";}
return $temp_sum/$dev_count;
}



function getBoundingBox($lat_degrees,$lon_degrees,$distance_in_km) {

	$radius = 3963.1; // of earth in miles
   
   $distance_in_miles=0.621371*$distance_in_km;

	// bearings
	$due_north = 0;
	$due_south = 180;
	$due_east = 90;
	$due_west = 270;

	// convert latitude and longitude into radians
	$lat_r = deg2rad($lat_degrees);
	$lon_r = deg2rad($lon_degrees);

	// find the northmost, southmost, eastmost and westmost corners $distance_in_miles away
	// original formula from
	// http://www.movable-type.co.uk/scripts/latlong.html

	$northmost  = asin(sin($lat_r) * cos($distance_in_miles/$radius) + cos($lat_r) * sin ($distance_in_miles/$radius) * cos($due_north));
	$southmost  = asin(sin($lat_r) * cos($distance_in_miles/$radius) + cos($lat_r) * sin ($distance_in_miles/$radius) * cos($due_south));

	$eastmost = $lon_r + atan2(sin($due_east)*sin($distance_in_miles/$radius)*cos($lat_r),cos($distance_in_miles/$radius)-sin($lat_r)*sin($lat_r));
	$westmost = $lon_r + atan2(sin($due_west)*sin($distance_in_miles/$radius)*cos($lat_r),cos($distance_in_miles/$radius)-sin($lat_r)*sin($lat_r));


	$northmost = rad2deg($northmost);
	$southmost = rad2deg($southmost);
	$eastmost = rad2deg($eastmost);
	$westmost = rad2deg($westmost);

	// sort the lat and long so that we can use them for a between query
	if ($northmost > $southmost) {
		$lat1 = $southmost;
		$lat2 = $northmost;

	} else {
		$lat1 = $northmost;
		$lat2 = $southmost;
	}


	if ($eastmost > $westmost) {
		$lon1 = $westmost;
		$lon2 = $eastmost;

	} else {
		$lon1 = $eastmost;
		$lon2 = $westmost;
	}

	return array($lat1,$lon1,$lat2,$lon2);
	
}
?>
 



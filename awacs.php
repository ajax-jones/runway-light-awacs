define("INTERVAL", (20 * 1) );  
	function fexp() {  
	        $lat = "your latitude";
	        $lon = "your longitude";
			$side = 15.75;
	        $box  = get_bounding_box($lat,$lon,$side);
			$latmin = $box[0];
			$lonmin = $box[1];
			$latmax = $box[2];
			$lonmax = $box[3];
	        $flyurl = "https://opensky-network.org/api/states/all?lamin=$latmin&lomin=$lonmin&lamax=$latmax&lomax=$lonmax";
	        echo "Scanning the SKY";
	        $start_time = microtime(true);
	        $json   = file_get_contents($flyurl);
	        $data   = json_decode($json, TRUE);
			$inbound = FALSE;
			$num_planes = count($data['states']);
			if ($num_planes >0)
			{
				echo " and we can see $num_planes planes\n ";
				for ($x =0; $x < $num_planes; $x++)
				{
					error_reporting(E_ALL);
					$icao24   	= $data['states'][$x][0];
		        	$callsign 	= $data['states'][$x][1];
        			$country  	= $data['states'][$x][2];
					$baro_altitude 	= $data['states'][$x][7];
					$geo_altitude_m	= round($data['states'][$x][13]);
					$geo_altitude_f = round(($geo_altitude_m/0.3048));
        			$air_speed_ms   = $data['states'][$x][9];
					$air_speed_kmh  = round( ($air_speed_ms * 3600)/1000   );
					$air_speed_mph  = round( (($air_speed_ms * 3600)/1000)*.621371   );
					$air_speed_kts  = round( ((($air_speed_ms * 3600)/1000)*.621371)*.868976242   );
					$heading	= $data['states'][$x][10];
					$heading_d	= round($heading);
					$latitude	= $data['states'][$x][6];
					$longitude	= $data['states'][$x][5];
					$plane_heading  = get_bearing($lat,$lon,$latitude,$longitude);
					$distplane      = get_distHaversine($lat,$lon,$latitude,$longitude);
					$intercept      = get_intercept($plane_heading,$heading_d,$distplane);
					if ($air_speed_kmh > 0) {
						$plane_eta 	= $distplane/$air_speed_kmh;
					}else {
						$eta = 1;
					}
					if (    ( ($intercept)<5 &&($intercept>0) ) &&    ($distplane<12) && $geo_altitude_m>0){
		       			$inbound = TRUE;
						echo "--------------------------------------------------------------------\n";
						echo "$icao24 - [$country $callsign] at [$geo_altitude_m M -- $geo_altitude_f ft] ";
						echo "[speed  $air_speed_kmh kmh and ",round($distplane,1),"km away]\n";
						echo "[on a heading of ",round($plane_heading,1),"] [homeangle $heading_d] ";
						echo "[$latitude,$longitude]\n";
						echo "[flypast in ",decimal_to_time($plane_eta)," now  ",round($intercept,1),"km away\n";
						echo "--------------------------------------------------------------------\n";
				        $DBi = new mysqli("127.0.0.1", "root", "your password", "awacs");
				        $sql = "select * from aircraftdatabase where `icao24`='$icao24'";
        				mysqli_set_charset($DBi,"utf8");
        				$getplanedata = mysqli_query($DBi, $sql) or die(mysqli_error($DBi));
        				$row_getplanedata = mysqli_fetch_assoc($getplanedata);
        				$rows_getplanedata = mysqli_num_rows($getplanedata);
        				if($rows_getplanedata>0) {
							do {
                				echo "callsign=";
                				echo $row_getplanedata['registration'];
                				echo " is a ";
                				echo $row_getplanedata['manufacturername'];
                				echo " ";
                				echo $row_getplanedata['model'];
                				echo " by ";
                				echo $row_getplanedata['manufacturericao'];
                				echo " owned by  ";
                				echo $row_getplanedata['owner'];
                				echo " seen  ";
                				echo $row_getplanedata['visits'];
                				echo " times  ";
                				echo " special rating=";
                				echo $row_getplanedata['special'];
                				echo "\n";
                				$visits = $row_getplanedata['visits']+1;
            				  } while ($row_getplanedata = mysqli_fetch_assoc($getplanedata));
							mysqli_free_result($getplanedata);
							$sqli = "UPDATE aircraftdatabase SET visits = $visits WHERE icao24 = '$icao24'";
							mysqli_set_charset($DBi,"utf8");
            				$updateplanedata = mysqli_query($DBi, $sqli) or die(mysqli_error($DBi));
						} else {
							echo "Couldn't find this plane in the DB so adding it";
							$sqli = "INSERT INTO aircraftdatabase (icao24,visits,special) VALUES ('$icao24',1,1)";
            				$updateplanedata = mysqli_query($DBi, $sqli) or die(mysqli_error($DBi));
						}
					echo "--------------------------------------------------------------------\n";
				} else {
					//				echo "$callsign ";
				}
			}
		} else {
       			echo " and the skies are clear\n ";
		}
		if ($inbound) {
			echo "Inbound plane\n";
			$command = "pigs w 17 1";
			execInBackground($command);
		} else {
			echo "no inbound flights\n";
			$command = "pigs w 17 0";
			execInBackground($command);
		}
	}
function decimal_to_time($decimal) {
	$offset = 0.002778;
	if ($decimal>$offset) {
		$decimal = $decimal - 0.002778;
	}
	$hours   = gmdate('H', floor($decimal * 3600));
	$minutes = gmdate('i', floor($decimal * 3600));
	$seconds = gmdate('s', floor($decimal * 3600));
    return str_pad($hours, 2, "0", STR_PAD_LEFT) . ":" . str_pad($minutes, 2, "0", STR_PAD_LEFT) . ":" . str_pad($seconds, 2, "0", STR_PAD_LEFT);
}
/*
 * calculate (initial) bearing between two points
 *
 * from: Ed Williams' Aviation Formulary, http://williams.best.vwh.net/avform.htm#Crs
 * source  = instantglobe.com/CRANES/GeoCoordTool.html
 */
function get_bearing($home_lat,$home_lon,$plane_lat,$plane_lon) {
	$lat1 = deg2rad($home_lat);
	$lat2 = deg2rad($plane_lat);
	$dLon = deg2rad($plane_lon-$home_lon);
	$y = sin($dLon) * cos($lat2);
	$x = cos($lat1)*sin($lat2) - sin($lat1)*cos($lat2)*cos($dLon);
	$z = atan2($y,$x);
	$zz = (rad2deg($z) +360)% 360;
  return $zz;
}
function get_intercept($home_head,$plane_head,$plane_distance) {
	$flight_angle =  abs(abs($home_head - $plane_head) - 180);
	$flight_angle_r = deg2rad($flight_angle);
	$flight_angle_t = tan($flight_angle_r);
	$flight_intercept = $flight_angle_t * $plane_distance;
  	return $flight_intercept;
}
/* - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -  */
/*
 * Use Haversine formula to Calculate distance (in km) between two points specified by
 * latitude/longitude (in numeric degrees)
 *
 * from: Haversine formula - R. W. Sinnott, "Virtues of the Haversine",
 *       Sky and Telescope, vol 68, no 2, 1984
 *       http://www.census.gov/cgi-bin/geo/gisfaq?Q5.1
 *
 * example usage from form:
 *   result.value = LatLon.distHaversine(lat1.value.parseDeg(), long1.value.parseDeg(),
 *                                       lat2.value.parseDeg(), long2.value.parseDeg());
 * where lat1, long1, lat2, long2, and result are form fields
 * source  = instantglobe.com/CRANES/GeoCoordTool.html
 */
function get_distHaversine ($home_lat,$home_lon,$plane_lat,$plane_lon) {
  $R = 6371; // earth's mean radius in km
  $dLat = deg2rad($plane_lat-$home_lat);
  $dLon = deg2rad($plane_lon-$home_lon);
  $lat1 = deg2rad($home_lat);
  $lat2 = deg2rad($plane_lat);
  $a = sin($dLat/2) * sin($dLat/2) + cos($lat1) * cos($lat2) * sin($dLon/2) * sin($dLon/2);
  $c = 2 * atan2(sqrt($a), sqrt(1-$a));
  $d = $R * $c;
  return $d;
}
function get_bounding_box($latitude_in_degrees, $longitude_in_degrees, $half_side_in_miles){
    $half_side_in_km = $half_side_in_miles * 1.609344;
    $lat = deg2rad($latitude_in_degrees);
    $lon = deg2rad($longitude_in_degrees);
    $radius  = 6371;
    # Radius of the parallel at given latitude;
    $parallel_radius = $radius*cos($lat);
    $lat_min = $lat - $half_side_in_km/$radius;
    $lat_max = $lat + $half_side_in_km/$radius;
    $lon_min = $lon - $half_side_in_km/$parallel_radius;
    $lon_max = $lon + $half_side_in_km/$parallel_radius;
    $box_lat_min = rad2deg($lat_min);
    $box_lon_min = rad2deg($lon_min);
    $box_lat_max = rad2deg($lat_max);
    $box_lon_max = rad2deg($lon_max);
    return array($box_lat_min,$box_lon_min,$box_lat_max,$box_lon_max);
}
function execInBackground($cmd) {
    if (substr(php_uname(), 0, 7) == "Windows"){
        pclose(popen("start /B ". $cmd, "r"));
    }
    else {
        exec($cmd . " > /dev/null &");
    }
}
function checkForStopFlag() { // completely optional
        return(TRUE);
}
function start() {
    echo "starting\n";
    $command = "pigs w 17 1";
    execInBackground($command);
    $active = TRUE;
    while($active) {
        usleep(1000); // optional, if you want to be considerate
        if (microtime(true) >= $nextTime) {
		fexp();
            	$nextTime = microtime(true) + INTERVAL;
        }
        $active = checkForStopFlag();
    }
}
fexp();
start();
?>

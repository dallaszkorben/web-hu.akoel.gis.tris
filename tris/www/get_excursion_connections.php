<?php
	include 'common.php';

	error_reporting(E_ALL);
	ini_set('display_errors', 1); //Needs to be here to see error message on the page, otherwise it ignors the error

	$ini = parse_ini_file( "tris.ini", true );

	$dbhost = $ini["database"]["dbhost"];
	$dbport = $ini["database"]["dbport"];
	$dbname = $ini["database"]["dbname"];
	$dbuser = $ini["database"]["dbuser"];
	$dbpwd = $ini["database"]["dbpwd"];
	
	$accomodationIconPath = $ini["paths"]["accomodation_icons_path"];

	//GET parameters
	$excursionId = $_GET[ 'excursionid' ];
	
	// Create DB connection
	$conn_string = "host=".$dbhost . " port=". $dbport . " dbname=" . $dbname . " user=" . $dbuser . " password=" . $dbpwd;
	$dbconn = pg_connect($conn_string);
	if(!$dbconn){
		echo "Can not connect to database";
		exit;
	}
	//pg_setclientencoding( 'utf-8', $dbconn );
	
	//XML preparation
	$xmlResult = new DOMDocument('1.0', 'UTF-8');

	$xmlExcursion = $xmlResult->appendChild($xmlResult->createElement("excursion"));
	$xmlExcursion->setAttribute( "id", $excursionId );
	
	//
	//<tours>
	//
	$xmlTours = $xmlExcursion->appendChild( $xmlResult->createElement("tours") );
		
	//array_to_json(t.route) as route,	
	$tour_list = pg_query($dbconn, "
			SELECT 
				e.date_start as date_start, 
				t.id as id, 
				t.route, 
				t.timestamp as time, 
				tt.name as type, 
				t.day as day 
			FROM excursions e, tours t, tour_type tt 
			WHERE e.id=" . $excursionId . " AND t.excursion_id=e.id AND t.tour_type_id=tt.id"
	);

	if (!$tour_list) {
		echo pg_last_error();
		exit;
	}

	//Through the tour list
	while ($tour_row = pg_fetch_assoc($tour_list)) {
		
		//<tour>
		$xmlTour = $xmlTours->appendChild( $xmlResult->createElement("tour") );
		$xmlTour->setAttribute( "id", $tour_row['id'] );		
		$xmlTour->setAttribute( "type", getTranslation( $tour_row['type'] ) );
		
		$shifted_date = getDayShiftedDate( $tour_row['date_start'], ($tour_row['day']-1) );
		
		//$start_time =  strtotime( $tour_row['date_start']);
		//$next_time =  strtotime( '+' . ($tour_row['day']-1) . ' day', $start_time );
		//$date = date( "Y.m.d", $next_time);
		
		$xmlTour->setAttribute( "day", $shifted_date );
			
		//$array = json_decode($tour_row['route']);
		//$array = json_decode( str_replace( '}', ']', str_replace('{', '[', $tour_row['route'] ) ) );
		$array = getSQLArrayToPHPArray( $tour_row['route'] );
		foreach( $array as $coord ){
				
			//<route>
			$xmlRoute = $xmlTour->appendChild( $xmlResult->createElement("route") );
			$xmlRoute->setAttribute( "lat", $coord['0'] );
			$xmlRoute->setAttribute( "lng", $coord['1'] );
			
		}	
	}

	//
	//<accomodations>
	//
	$xmlAccomodations = $xmlExcursion->appendChild( $xmlResult->createElement("accomodations") );

	//array_to_json(aa.days) as days, 
	//array_to_json(a.position) as accomodation_position,
	$accomodation_list = pg_query($dbconn, "
			SELECT 
				e.date_start as date_start, 
				a.id as accomodation_id, 
				aa.days as days, 
				a.name as accomodation_name, 
				a.address as accomodation_address, 
				a.position as accomodation_position, 
				t.name as accomodation_type,
				a.icon_name as accomodation_icon_name
			FROM excursions e, actual_accomodation aa, accomodations a, accomodation_type t 
			WHERE e.id=" . $excursionId . " AND e.id=aa.excursion_id AND aa.accomodation_id=a.id AND a.accomodation_type_id=t.id 
			ORDER BY aa.days[0]"
	);
	
	if (!$accomodation_list) {
		echo pg_last_error();
		exit;
	}
	
	//Through the accomodation list
	while ($accomodation_row = pg_fetch_assoc($accomodation_list)) {
	
		//<accomodation>
		$xmlAccomodation = $xmlAccomodations->appendChild( $xmlResult->createElement("accomodation") );
		$xmlAccomodation->setAttribute( "accomodation_id", $accomodation_row['accomodation_id'] );
		$xmlAccomodation->setAttribute( "accomodation_name", $accomodation_row['accomodation_name'] );
		$xmlAccomodation->setAttribute( "accomodation_address", $accomodation_row['accomodation_address'] );
		$xmlAccomodation->setAttribute( "accomodation_type", getTranslation( $accomodation_row['accomodation_type'] ) );
		$xmlAccomodation->setAttribute( "accomodation_icon", $accomodationIconPath.$accomodation_row['accomodation_icon_name'] );
			
		//$position_array = json_decode($accomodation_row['accomodation_position']);
		//$position_array = json_decode( str_replace( '}', ']', str_replace('{', '[', $accomodation_row['accomodation_position'] ) ) );
		$position_array = getSQLArrayToPHPArray( $accomodation_row['accomodation_position'] );
		$xmlAccomodation->setAttribute( "accomodation_lat", $position_array[0] );
		$xmlAccomodation->setAttribute( "accomodation_lng", $position_array[1] );
		
		//$days_array = json_decode($accomodation_row['days']);	
		//$days_array = json_decode( str_replace( '}', ']', str_replace('{', '[', $accomodation_row['days'] ) ) );
		$days_array = getSQLArrayToPHPArray( $accomodation_row['days'] );
		foreach( $days_array as $day ){
	
			$shifted_date = getDayShiftedDate( $accomodation_row['date_start'], ($day-1) );
			//$time =  strtotime( $accomodation_row['date_start']);
			//$time =  strtotime( '+' . ($day-1) . ' day', $time );				
			//$date_start = date( "Y.m.d", $time);
			
			//<day>
			$xmlDay = $xmlAccomodation->appendChild( $xmlResult->createElement("day", $shifted_date ) );
				
		}
	}	
	
	echo $xmlResult->saveXML();

?>

<?php
	/*
	ROSTER PROCESSING 
	* conn - SQL connection variable
	* team_id - team id
	* pid - player id
	* pick_id - pick id
	* tx_type - transaction type (matches z_txtypes)
	* new_sal - new salary (if changed)
	* dead_cap - array of dead cap hits for dropped players
	*
	*/
	function roster_px($conn,$team_id,$pid,$pick_id,$tx_type,$new_sal,$dead_cap){
		$year = $_SESSION['year'];
		$year2 = $year + 1;
		$year3 = $year + 2;
		
		$sql = "SELECT player_rs FROM prd_players WHERE player_id=$pid";
		$retval = mysqli_query($conn,$sql);
		while($row = mysqli_fetch_array($retval)) {
			if($row['player_rs']==1) {
				$sql2 = "UPDATE prd_players SET player_rs=0 WHERE player_id=$pid";
				$retval2 = mysqli_query($conn,$sql2);
			}
		}
		
		// promote
		if($tx_type == 1){
			$pbit = 0;
			$sql = "SELECT player_currentsalary,pbit FROM prd_players WHERE player_id=$pid";
			$retval = mysqli_query($conn, $sql);
			while($row = mysqli_fetch_array($retval)){
				$sal = $row['player_currentsalary'];
				if(!empty($row['pbit'])) {
					$pbit = 1;
				}
			}

			// promoting claimed player 
			if($new_sal > 0) {
				$sql = "UPDATE prd_players SET player_practicesquad=0,player_currentsalary=$new_sal,player_update_date=now(),pbit=NULL WHERE player_id=$pid";
				$retval = mysqli_query($conn, $sql);
				
				$sql = "UPDATE prd_psclaims SET tx_completed=1,tx_noadd_reason=\"Claim matched\" WHERE tx_claimedplayer_id=$pid AND tx_won=1 AND tx_completed=0"; 
				$retval = mysqli_query($conn, $sql);
				
				$sql = "INSERT INTO prd_postedtx (tx_instant,tx_type,tx_player_id,tx_originalteam_id,tx_newteam_id,tx_originalteam_salary,tx_newteam_salary) VALUES (now(),1,$pid,$team_id,$team_id,$sal,$new_sal)";
				$retval = mysqli_query($conn, $sql); 
				
				$sql = "INSERT INTO prd_playerhx (hx_pid,hx_date,hx_tid,hx_sal,hx_action) VALUES ($pid,now(),$team_id,$new_sal,$tx_type)";
				$retval = mysqli_query($conn,$sql);
			}
			/* $0 practice squad player */
			elseif($sal==0) {
				$sql = "UPDATE prd_players SET player_practicesquad=0,player_currentsalary=1,player_update_date=now(),pbit=NULL WHERE player_id=$pid";
				$retval = mysqli_query($conn, $sql);
				
				$sql = "INSERT INTO prd_postedtx (tx_instant,tx_type,tx_player_id,tx_originalteam_id,tx_newteam_id,tx_originalteam_salary,tx_newteam_salary) VALUES (now(),1,$pid,$team_id,$team_id,0,1)";
				$retval = mysqli_query($conn, $sql); 
				
				$sql = "INSERT INTO prd_playerhx (hx_pid,hx_date,hx_tid,hx_sal,hx_action) VALUES ($pid,now(),$team_id,1,$tx_type)";
				$retval = mysqli_query($conn,$sql);
			}
			/* $0 cap hit, but with salary (for draft picks) */
			else if($sal>0 AND $pbit==1) {
				$sql = "UPDATE prd_players SET player_practicesquad=0,player_update_date=now(),pbit=NULL WHERE player_id=$pid";
				$retval = mysqli_query($conn, $sql);

				$sql = "INSERT INTO prd_postedtx (tx_instant,tx_type,tx_player_id,tx_originalteam_id,tx_newteam_id,tx_originalteam_salary,tx_newteam_salary) VALUES (now(),1,$pid,$team_id,$team_id,0,$sal)";
				$retval = mysqli_query($conn, $sql); 
				
				$sql = "INSERT INTO prd_playerhx (hx_pid,hx_date,hx_tid,hx_sal,hx_action) VALUES ($pid,now(),$team_id,$sal,$tx_type)";
				$retval = mysqli_query($conn,$sql);
			}
			/* normal practice squad player with salary and cap hit $1+ */
			else {
				$sql = "UPDATE prd_players SET player_practicesquad=0,player_update_date=now(),pbit=NULL WHERE player_id=$pid";
				$retval = mysqli_query($conn, $sql);
				
				$sql = "INSERT INTO prd_postedtx (tx_instant,tx_type,tx_player_id,tx_originalteam_id,tx_newteam_id) VALUES (now(),1,$pid,$team_id,$team_id)";
				$retval = mysqli_query($conn, $sql);
				
				$sql = "INSERT INTO prd_playerhx (hx_pid,hx_date,hx_tid,hx_action) VALUES ($pid,now(),$team_id,$tx_type)";
				$retval = mysqli_query($conn,$sql);
			}
		}
		
		// demote
		if($tx_type == 2){
			$sql = "UPDATE prd_players SET player_practicesquad=1,player_update_date=now() WHERE player_id=$pid";
			$retval = mysqli_query($conn, $sql);
			
			$sql = "INSERT INTO prd_postedtx (tx_instant,tx_type,tx_player_id,tx_originalteam_id,tx_newteam_id) VALUES (now(),2,$pid,$team_id,$team_id)";
			$retval = mysqli_query($conn, $sql);
			
			$sql = "INSERT INTO prd_playerhx (hx_pid,hx_date,hx_tid,hx_action) VALUES ($pid,now(),$team_id,$tx_type)";
			$retval = mysqli_query($conn,$sql);
		}
		
		// drop
		if($tx_type == 5){
			$pbit = 0;
			$dropped_ar = 0;
			// get rid of pid in dead_cap[0]
			$pid_scrap = array_shift($dead_cap);
			$sql = "SELECT * FROM prd_players WHERE player_id=$pid";
			$retval = mysqli_query($conn, $sql);
			while($row = mysqli_fetch_array($retval)){
				$salary = $row['player_currentsalary'];
				if(!empty($row['pbit'])) {
					$pbit = 1;
				}
				$ryrs = contract_terms($conn,$row['player_contract_type'],0,3);
				if($row['player_practicesquad']==0 AND $row['player_injuredreserve']==0){
					$dropped_ar = 1;
				}
			}
			$sql = "UPDATE prd_players SET player_fantasyteam_ID=0,player_injuredreserve=0,player_practicesquad=0,player_currentsalary=0,player_update_date=now(),pbit=NULL WHERE player_id=$pid";
			$retval = mysqli_query($conn, $sql);
			
			if($salary > 0 AND $pbit == 0){
				if(!empty($dead_cap) AND count($dead_cap)>0){
					for($i=0;$i<$ryrs;$i++){
						$dead_year = $_SESSION['year']+$i;
						$dead_sal = $dead_cap[$i];
						
						if($dead_sal > 0){
							//echo $pid . " - " . $dead_year . " - " . $dead_sal . "<br>";
							$sql = "INSERT INTO prd_deadcap (dead_tid,dead_year,dead_pid,dead_cap) VALUES ($team_id,$dead_year,$pid,$dead_sal)";
							$retval = mysqli_query($conn,$sql);
						}
					}
				}
			}
			
			$dead_string = implode(",",$dead_cap);
			
			$sql = "INSERT INTO prd_postedtx (tx_instant,tx_type,tx_player_id,tx_originalteam_id,tx_newteam_id,tx_originalteam_salary,tx_newteam_salary,from_ar,tx_deadsal) VALUES (now(),5,$pid,$team_id,0,$salary,0,$dropped_ar,'$dead_string')";
			$retval = mysqli_query($conn, $sql);
			
			$sql = "INSERT INTO prd_playerhx (hx_pid,hx_date,hx_tid,hx_sal,hx_action) VALUES ($pid,now(),0,0,$tx_type)";
			$retval = mysqli_query($conn,$sql);
		}
		
		// activation
		if($tx_type==7){
			$sql = "UPDATE prd_players SET player_injuredreserve=0 WHERE player_id=$pid";
			$retval = mysqli_query($conn, $sql);
			
			$sql = "INSERT INTO prd_postedtx (tx_instant,tx_type,tx_player_id,tx_originalteam_id,tx_newteam_id) VALUES (now(),7,$pid,$team_id,$team_id)";
			$retval = mysqli_query($conn, $sql);
			
			$sql = "INSERT INTO prd_playerhx (hx_pid,hx_date,hx_tid,hx_action) VALUES ($pid,now(),$team_id,$tx_type)";
			$retval = mysqli_query($conn,$sql);
		}
		
		// inactivation
		if($tx_type==8){
			$sql = "UPDATE prd_players SET player_injuredreserve=1 WHERE player_id=$pid";
			$retval = mysqli_query($conn, $sql);
			
			$sql = "INSERT INTO prd_postedtx (tx_instant,tx_type,tx_player_id,tx_originalteam_id,tx_newteam_id) VALUES (now(),8,$pid,$team_id,$team_id)";
			$retval = mysqli_query($conn, $sql);
			
			$sql = "INSERT INTO prd_playerhx (hx_pid,hx_date,hx_tid,hx_action) VALUES ($pid,now(),$team_id,$tx_type)";
			$retval = mysqli_query($conn,$sql);
		}
		
		// FA draft
		if($tx_type==10){
			$sql = "UPDATE prd_players SET player_fantasyteam_ID=$team_id,player_currentsalary=$new_sal,player_practicesquad=0,player_injuredreserve=0,player_contract_type=\"ABo\",player_update_date=now() WHERE player_id=$pid";
			$retval = mysqli_query($conn,$sql);
			
			$sql = "INSERT INTO prd_playerhx (hx_pid,hx_date,hx_tid,hx_sal,hx_action) VALUES ($pid,now(),$team_id,$new_sal,$tx_type)";
			$retval = mysqli_query($conn,$sql);
		}
	}

	/*
	ROSTER SPACE?
	RETURNS: 0 if roster space for proposed moves, error message if no room
	* conn - SQL connection variable
	* team_id - team id
	* rest - arrays (ar_claim for players getting claimed TO AR, and ps_claim for players getting claimed TO PS)
	*
	*/
	function roster_space($conn,$team_id,$promote,$demote,$drop,$inactivate,$activate,$ar_claim,$ps_claim,$traded_for,$traded_away){
		$ar_count = roster_count($conn,$team_id,1);
		$ps_count = roster_count($conn,$team_id,2);
		$ir_count = roster_count($conn,$team_id,3); 
		
		$year = $_SESSION['year'];
		// new luxury tax rule for PS spots
		$cap = cap_hit($conn,$team_id,0);
		
		if($cap > 300 AND $_SESSION['period']!=3){
			$ps_spots = 6 - ceil(($cap-300)/5);
		}
		else{
			$ps_spots = 6;
		}
				
		// pending practice squad claims
		if($_SESSION['period']==0 OR $_SESSION['period']==10){
			// add one spot to AR per pending claim and add cap for bid
			$sql = "SELECT tx_id,tx_bid FROM prd_psclaims WHERE tx_won=1 AND tx_claimteam_id=$team_id AND tx_completed=0";
			$retval = mysqli_query($conn,$sql);
			while($row = mysqli_fetch_array($retval)){
				$ar_count++;
			}
			
			// if demoting a player as part of a pending claim (and player is still on team's AR)
			$sql = "SELECT tx_demotedplayer_id,player_practicesquad,player_injuredreserve,player_fantasyteam_ID FROM prd_psclaims LEFT JOIN prd_players ON tx_demotedplayer_id=player_id WHERE tx_won=1 AND tx_claimteam_id=$team_id AND tx_completed=0 AND tx_demotedplayer_id>0";
			$retval = mysqli_query($conn,$sql);
			while($row = mysqli_fetch_array($retval)){
				if($row['player_practicesquad']==0 AND $row['player_injuredreserve']==0 AND $row['player_fantasyteam_ID']==$team_id){
					$ar_count--;
					$ps_count++;
				}
			}
			
			// if dropping a player in claim (and player is still on team)
			$sql = "SELECT tx_droppedplayer_id,player_practicesquad,player_fantasyteam_ID,player_injuredreserve FROM prd_psclaims LEFT JOIN prd_players ON tx_droppedplayer_id=player_id WHERE tx_won=1 AND tx_claimteam_id=$team_id AND tx_completed=0 AND tx_droppedplayer_id>0";
			$retval = mysqli_query($conn,$sql);
			while($row = mysqli_fetch_array($retval)){
				if($row['player_fantasyteam_ID']==$team_id){
					if($row['player_practicesquad']==1){
						$ps_count--;
					}
					elseif($row['player_injuredreserve']==1){
						$ir_count--;
					}
					else{
						$ar_count--;
					}
				}
			}
		}
		
		
		$ar_count += count($promote);
		$ps_count -= count($promote);
		
		if(count($demote) > 0){
			foreach($demote as $check){
				$sql = "SELECT player_practicesquad,player_injuredreserve,player_fantasyteam_ID FROM prd_players WHERE player_id=$check";
				$retval = mysqli_query($conn,$sql);
				while($row = mysqli_fetch_array($retval)){
					if($row['player_fantasyteam_ID']==$team_id){
						if($row['player_practicesquad']==0 AND $row['player_injuredreserve']==0){
							$ar_count--;
							$ps_count++;
						}
					}
				}
			}
		}
		
		// update this to account for PS players being inactivated/activated
		foreach($inactivate as $check) {
			$ir_count++;
			$sql = "SELECT player_practicesquad FROM prd_players WHERE player_id=$check";
			$retval = mysqli_query($conn,$sql);
			while($row = mysqli_fetch_array($retval)) {
				if($row['player_practicesquad']==1) {
					$ps_count--;
				}
				else {
					$ar_count--;
				}
			}
		}
		
		foreach($inactivate as $check) {
			$ir_count--;
			$sql = "SELECT player_practicesquad FROM prd_players WHERE player_id=$check";
			$retval = mysqli_query($conn,$sql);
			while($row = mysqli_fetch_array($retval)) {
				if($row['player_practicesquad']==1) {
					$ps_count++;
				}
				else {
					$ar_count++;
				}
			}
		}
		
		foreach($traded_for as $check){
			$sql = "SELECT player_practicesquad FROM prd_players WHERE player_id=$check";
			$retval = mysqli_query($conn,$sql);
			while($row = mysqli_fetch_array($retval)){
				if($row['player_practicesquad']==1){
					$ps_count++;
				}
				else{
					$ar_count++;
				}
			}
		}
		
		foreach($traded_away as $check){
			$sql = "SELECT player_practicesquad FROM prd_players WHERE player_id=$check";
			$retval = mysqli_query($conn,$sql);
			while($row = mysqli_fetch_array($retval)){
				if($row['player_practicesquad']==1){
					$ps_count--;
				}
				else{
					$ar_count--;
				}
			}
		}
		
		if(count($drop) > 0){
			foreach($drop as $check){
				$sql = "SELECT player_practicesquad,player_injuredreserve,player_fantasyteam_ID FROM prd_players WHERE player_id=$check";
				$retval = mysqli_query($conn,$sql);
				while($row = mysqli_fetch_array($retval)){
					if($row['player_fantasyteam_ID']==$team_id){
						if($row['player_practicesquad']==1){
							$ps_count--;
						}
						elseif($row['player_injuredreserve']==1){
							$ir_count--;
						}
						else{
							$ar_count--;
						}
					}
				}
			}
		}
		
		$ar_count += count($ar_claim);
		$ps_count += count($ps_claim);
				
		if(($ar_count<=19) AND ($ps_count<=$ps_spots)){ // no IR limit for 2020 season (COVID-19)
			return array(0,"");
		}
		else{
			$string = "Roster limits exceeded. Roster counts with this move: " . $ar_count . "/19 (AR), " . $ps_count . "/" . $ps_spots . " (PS), " . $ir_count . " (IR)"; // no IR limit for 2020 season (COVID-19)
			return array(1,$string);
		}
		
	}
	
	/*
	CAP_HIT
	RETURNS: cap hit for given year
	* conn - SQL connection variable
	* team_id - team id
	* year_inc - year to get cap for (0 - current year, add one for each subsequent year)
	*
	*/
	function cap_hit($conn,$team_id,$year_inc) {
		$year = $_SESSION['year'];
		$sals = 0;
		
		// traded cap
		$sql = "SELECT * FROM prd_tradedcap WHERE cap_year=($year+$year_inc)";
		$retval = mysqli_query($conn,$sql);
		if(mysqli_num_rows($retval) > 0){
			$sql = "SELECT cap_tradedaway FROM prd_tradedcap WHERE cap_year=($year+$year_inc) AND cap_tid=$team_id";
			$retval = mysqli_query($conn, $sql);
			while($row = mysqli_fetch_array($retval)) {
				$sals += $row['cap_tradedaway'];
			}
		}
		
		// dead cap
		$sql = "SELECT SUM(dead_cap) dead FROM prd_deadcap WHERE dead_tid=$team_id AND dead_year=($year+$year_inc)";
		$retval = mysqli_query($conn,$sql);
		while($row = mysqli_fetch_array($retval)) {
			$sals += $row['dead'];
		}
		
		// current roster (except IR if this year's)
		if($year_inc == 0) {
			$sql = "SELECT player_currentsalary,player_contract_type FROM prd_players WHERE player_fantasyteam_ID=$team_id AND player_injuredreserve=0 AND pbit IS NULL";
			$retval = mysqli_query($conn, $sql);
			while($row = mysqli_fetch_array($retval)) {
				$sals += $row['player_currentsalary'];
			}
		}
		else {
			$sql = "SELECT player_currentsalary,player_contract_type FROM prd_players WHERE player_fantasyteam_ID=$team_id AND pbit IS NULL";
			$retval = mysqli_query($conn, $sql);
			while($row = mysqli_fetch_array($retval)) {
				$yrs_rem = contract_terms($conn,$row['player_contract_type'],0,3);
				if($year_inc < $yrs_rem) {
					$sals += $row['player_currentsalary'];
				}
			}
		}
		
		return $sals;
	}
	
	
	/*
	CAP SPACE?
	RETURNS: 0 if cap space for proposed moves, error message if not
	* conn - SQL connection variable
	* team_id - team id
	* promote - add 1 if no salary yet, add claimed amount if protecting
	* drop - dead cap calculation
	* inactivate - subtract cap
	* activate - add cap
	* added_cap - cap added through FA draft, waiver claims
	*
	*/
	function cap_space($conn,$team_id,$promote,$drop,$inactivate,$activate,$traded_for,$traded_away,$added_cap){
		$year = $_SESSION['year'];
		
		$ar_rem = 18 - roster_count($conn,$team_id,1);
		
		$cap = array(0,0,0,0,0,0,0,0,0,0);
		$dead_cap = array(0,0,0,0,0,0,0,0,0,0);
		
		for($i=0;$i<10;$i++){
			$cap[$i] = cap_hit($conn,$team_id,$i);
			
			$sql = "SELECT SUM(dead_cap) dead FROM prd_deadcap WHERE dead_tid=$team_id AND dead_year=($year+$i)";
			$retval = mysqli_query($conn,$sql);
			while($row = mysqli_fetch_array($retval)) {
				$dead_total = $row['dead'];
				$dead_cap[$i] = $row['dead'];
			}
		}
		
		// add cap for bid for pending ps claims
		$sql = "SELECT tx_bid,player_contract_type FROM prd_psclaims LEFT JOIN prd_players ON tx_claimedplayer_id=player_id WHERE tx_won=1 AND tx_claimteam_id=$team_id AND tx_completed=0";
		$retval = mysqli_query($conn,$sql);
		while($row = mysqli_fetch_array($retval)){
			$yrs = contract_terms($conn,$row['player_contract_type'],0,3);
			for($i=0;$i<$yrs;$i++){
				$cap[$i] += $row['tx_bid'];
			}	
		}
		
		// FA draft bids
		$sql = "SELECT SUM(pick_proxy) as outst_bids FROM prd_fadraft WHERE pick_team=$team_id AND pick_year=$year AND pick_active=1";
		$retval = mysqli_query($conn,$sql);
		while($row = mysqli_fetch_array($retval)){
			$cap[0] += $row['outst_bids'];
			$cap[1] += $row['outst_bids'];
		}
		
		// promotions
		if(count($promote)>0){
			foreach($promote as $check){
				$sql = "SELECT pbit,player_contract_type,player_currentsalary FROM prd_players WHERE player_id=$check AND pbit IS NULL";
				$retval = mysqli_query($conn,$sql);
				while($row = mysqli_fetch_array($retval)) {
					$yrs_rem = contract_terms($conn,$row['player_contract_type'],0,3);
					for($i=0; $i<$yrs_rem; $i++) {
						if($row['player_currentsalary']>0) {
							$cap[$i] += $row['player_currentsalary'];
						}
						else {
							$cap[$i] += 1;
						}
					}
				}
			}
		}
		
		if(count($drop) > 0) {
			foreach($drop as $check) {
				$sql = "SELECT player_practicesquad,player_injuredreserve,player_contract_type,player_currentsalary,player_retired FROM prd_players WHERE player_id=$check AND pbit IS NULL";
				$retval = mysqli_query($conn,$sql);
				while($row = mysqli_fetch_array($retval)){
					$contract_type = $row['player_contract_type'];
					$sal = $row['player_currentsalary'];
					$retired = $row['player_retired'];
					$yrs_rem = contract_terms($conn,$contract_type,0,3);
					for($i=0;$i<$yrs_rem;$i++){
						if($retired==1){
							$dead_act += ceil(0.25*$sal);
						}
						else {
							$dead_act = $sal;
						}
						$dead_total += $dead_act;
						$dead_cap[$i] += $dead_act;
					}
				}
			}
		}
		
		// inactivations and activations
		if(count($inactivate) > 0){
			foreach($inactivate as $check){
				$sql = "SELECT player_currentsalary FROM prd_players WHERE player_id=$check AND pbit IS NULL";
				$retval = mysqli_query($conn,$sql);
				while($row = mysqli_fetch_array($retval)) {
					$cap[0] -= $row['player_currentsalary'];
				}
			}
		}
		
		if(count($activate) > 0){
			foreach($activate as $check){
				$sql = "SELECT player_currentsalary FROM prd_players WHERE player_id=$check AND pbit IS NULL";
				$retval = mysqli_query($conn,$sql);
				while($row = mysqli_fetch_array($retval)) {
					$cap[0] += $row['player_currentsalary'];
				}
			}
		}
		
		// trades
		if(count($traded_for) > 0){
			foreach($traded_for as $check){
				$sql = "SELECT player_contract_type,player_currentsalary FROM prd_players WHERE player_id=$check AND pbit IS NULL";
				$retval = mysqli_query($conn,$sql);
				while($row = mysqli_fetch_array($retval)){
					$yrs = contract_terms($conn,$row['player_contract_type'],0,3);
					for($i=0;$i<$yrs;$i++){
						$cap[$i] += $row['player_currentsalary'];
					}
				}
			}
		}
		
		if(count($traded_away) > 0){
			foreach($traded_away as $check){
				$sql = "SELECT player_contract_type,player_currentsalary FROM prd_players WHERE player_id=$check AND pbit IS NULL";
				$retval = mysqli_query($conn,$sql);
				while($row = mysqli_fetch_array($retval)){
					$yrs = contract_terms($conn,$row['player_contract_type'],0,3);
					for($i=0;$i<$yrs;$i++){
						$cap[$i] -= $row['player_currentsalary'];
					}
				}
			}
		}
				
		if($added_cap != 0){
			$cap[0] += $added_cap;
			$cap[1] += $added_cap;
		}
				
		for($i=0;$i<5;$i++){
			$calc_year = $year + $i;

			if($cap[$i] > (330-$ar_rem)) {
				$string = "Salary cap exceeded. Cap with this move for " . $calc_year . " would be $" . $cap[$i];
				if($ar_rem>0){
					$string = $string . " and you have " . $ar_rem . " active roster spots to fill.";
				}
				return array(1,$string);
			}
			/*elseif($_SESSION['cap']==1 AND ($dead_cap[$i] > 100)) {
				$string = "Dead cap limit exceeded. Your dead cap for " . $calc_year . " would be $" . $dead_cap[$i];
				return array(1,$string);
			}*/
			elseif($dead_total > 180) {
				$string = "Dead cap limit exceeded. Your total dead cap would be $" . $dead_total;
				return array(1,$string);
			}
		}
		return array(0,""); 
	}
	
	/*
	ROSTER COUNT
	RETURNS: player count for indicated type
	* conn - SQL connection variable
	* team_id - team id
	* type - 0 for all, 1 for AR, 2 for PS, 3 for IR
	*
	*/
	function roster_count($conn,$team_id,$type){
		if($type == 0){
			$sql = "SELECT player_id FROM prd_players WHERE player_fantasyteam_ID=$team_id";
			$retval = mysqli_query($conn,$sql);
			return mysqli_num_rows($retval);
		}
		elseif($type == 1){
			$sql = "SELECT player_id FROM prd_players WHERE player_fantasyteam_ID=$team_id AND player_injuredreserve=0 AND player_practicesquad=0";
			$retval = mysqli_query($conn,$sql);
			return mysqli_num_rows($retval);
		}
		elseif($type == 2){
			$sql = "SELECT player_id FROM prd_players WHERE player_fantasyteam_ID=$team_id AND player_injuredreserve=0 AND player_practicesquad=1";
			$retval = mysqli_query($conn,$sql);
			return mysqli_num_rows($retval);
		}
		elseif($type == 3){
			$sql = "SELECT player_id FROM prd_players WHERE player_fantasyteam_ID=$team_id AND player_injuredreserve=1";
			$retval = mysqli_query($conn,$sql);
			return mysqli_num_rows($retval);
		}
	}
		
	/*
	DRAFT PICK DISPLAY
	* conn - SQL connection variable
	* tid - team id
	* year - year of picks
	* show_num - 1 to display current pick #
	* my_team - user's team id
	*/
	function draft_display($conn,$tid,$year,$show_num,$my_team){
		echo "<div class=\"table-responsive\">
			<table class=\"table table-bordered table-hover table-striped\">
				<thead>
					<tr>
						<th>Year</th>
						<th>Team</th>
						<th>Round</th>";
						if($show_num==1){
							echo "<th>#</th>";
							echo "<th>Salary</th>";
						}
						if($my_team>0){
							echo "<th>Actions</th>";
						}
					echo
					"</tr>
				</thead>
				<tbody>";
							$sql = "SELECT pick_id,pick_year,pick_round,pick_number,team_city,pick_cost FROM prd_draftpicks LEFT JOIN z_fantasyteam ON prd_draftpicks.pick_pickingteam=z_fantasyteam.player_fantasyteam_ID LEFT JOIN z_draftsal ON (pick_round=sal_round AND pick_number=sal_pick) WHERE pick_owningteam=$tid AND pick_year=$year ORDER BY pick_year,pick_round,pick_number";
							$retval = mysqli_query($conn, $sql);
							while($row = mysqli_fetch_array($retval)){
								$pick_id = $row['pick_id'];
								echo "<tr class=\"active\">";
								echo "<td>" . $row['pick_year'] . "</td>";
								echo "<td>" . $row['team_city'] . "</td>";
								echo "<td>" . $row['pick_round'] . "</td>";
								if($show_num==1){
									if($row['pick_number']>0){
										echo "<td>" . $row['pick_number'] . "</td>";
									}
									else {
										echo "<td>--</td>";
									}
									echo "<td>$" . $row['pick_cost'] . "</td>";
								}
								if($my_team>0){
									echo "<td>";
									if($tid==$my_team) {
										if($_SESSION['locked']==0 AND $_SESSION['week']<=12){
											echo "<a button type=\"button\" href=\"/trades_new.php?pckid=$pick_id\" class=\"btn btn-xs btn-info\" style=\"margin-left:2px\"><i class=\"glyphicon glyphicon-transfer\"></i></button></a>";
										}
										$sql2 = "SELECT * FROM prd_tradeblock WHERE tb_pick_id=$pick_id AND tb_team_id=$tid";
										$retval2 = mysqli_query($conn,$sql2);
										if(mysqli_num_rows($retval2)==0){
											echo "<button type=\"submit\" name=\"add_tb_pick\" value=$pick_id class=\"btn btn-xs btn-default\" style=\"margin-left:2px\"><i class=\"glyphicon glyphicon-tag\"></i></button></a>";											
										}
										else{
											echo "<button type=\"submit\" name=\"rem_tb_pick\" value=$pick_id class=\"btn btn-xs btn-danger\" style=\"margin-left:2px\"><i class=\"glyphicon glyphicon-minus\"></i></button></a>";																													
										}
									}
									elseif($_SESSION['locked']==0 AND $_SESSION['week']<=12) {
										echo "<a button type=\"button\" href=\"/trades_new.php?pckid=$pick_id&tid=$tid\" class=\"btn btn-xs btn-success\" style=\"margin-left:2px\"><i class=\"glyphicon glyphicon-transfer\"></i></button></a>";
									}																		
									echo "</td>";
								}
								echo "</tr>";
							}
				echo
				"</tbody>
			</table>
		</div>";
	}

	/* 
	IS LOCKED?
	RETURNS: 1 if locked, 0 if not, 2 if only locked from promotion, 3 if locked to AR except for trade, 4 if FA ineligibile to be picked up
	* conn - SQL connection variable
	* pid - player_id
	* update_date - last player update date
	*
	*/
	function is_locked($conn,$pid,$update_date){
		if($_SESSION['locked']==1){
			return 1;
		}
		elseif($_SESSION['period']>=3){
			return 0;
		}
		
		
		// locked if being dropped or demoted in won (but not processed yet) practice squad claim
		if($_SESSION['week']>=1 AND $_SESSION['week']<13){
			$sql = "SELECT tx_id FROM prd_psclaims WHERE tx_won=1 AND (tx_demotedplayer_id=$pid OR tx_droppedplayer_id=$pid) AND tx_completed=0";
			$retval = mysqli_query($conn,$sql);
			if(mysqli_num_rows($retval)>0){
				return 1;
			}
		}
			
		$game_col = $_SESSION['time'];
		$week = $_SESSION['week'];
		
		$th_lock = 0;
		$sa_lock = 0;
		$su_lock = 0;
		$mo_lock = 0;
		if($update_date!=0){
			$deadline = date(strtotime($update_date));
		}
		
		date_default_timezone_set('America/Chicago');
		$eog = date(strtotime('next tuesday 3:00 AM'));
		$datenow = date(strtotime('now'));
		$eog_diff = $eog - $datenow;
		if($eog_diff<374400){
			$th_lock=1;
			
			if($eog_diff<237600){
				$sa_lock=1;
				
				if($eog_diff<140400){
					$su_lock=1;
					
					if($eog_diff<28800){
						$mo_lock=1;
					}
				}
			}
		}
		
		$sql = "SELECT player_fantasyteam_ID FROM prd_players WHERE player_id=$pid";
		$retval = mysqli_query($conn,$sql);
		while($row = mysqli_fetch_array($retval)) {
			$pteam = $row['player_fantasyteam_ID'];
		}
		
		/*if($th_lock == 1){
			$sql = "SELECT $game_col,`$week`,player_fantasyteam_ID FROM prd_players LEFT JOIN nfl_schedule ON player_nfl_team=TEAM WHERE player_id=$pid";
			$retval = mysqli_query($conn,$sql);
			while($row = mysqli_fetch_array($retval)){
				if(($row[$game_col]=="R" AND $th_lock==1) OR ($row[$game_col]=="S" AND $sa_lock==1) OR ($row[$game_col]==NULL AND $su_lock==1) OR ($row[$game_col]=="M" AND $mo_lock==1)) {
					return 1;
				}
				$pteam = $row['player_fantasyteam_ID'];
			}
		}*/
		
		if(!isset($pteam) OR $pteam > 0){
			// locked from promotion if not on PS for a full week (two Thursdays)
			$sql = "SELECT player_practicesquad,player_fantasyteam_ID FROM prd_players WHERE player_id=$pid";
			$retval = mysqli_query($conn,$sql);
			while($row = mysqli_fetch_array($retval)){
				$ps = $row['player_practicesquad'];
				$tid = $row['player_fantasyteam_ID'];
			}
			if($tid > 0){
				if($ps == 1){
					$sql = "SELECT tx_instant FROM prd_postedtx WHERE tx_newteam_id=$tid AND tx_player_id=$pid AND tx_type=2 ORDER BY tx_id DESC LIMIT 1";
					$retval = mysqli_query($conn,$sql);
					while($row = mysqli_fetch_array($retval)){
						$start = date( "Y-m-d 05:00:00", strtotime("-1 week"));
						if($row['tx_instant']>$start){
							return 2;
						}
					}
				}
				else{
					$sql = "SELECT tx_instant FROM prd_postedtx WHERE tx_newteam_id=$tid AND tx_player_id=$pid AND (tx_type=1 OR tx_type=4) ORDER BY tx_id DESC LIMIT 1";
					$retval = mysqli_query($conn,$sql);
					while($row = mysqli_fetch_array($retval)){
						$start = date( "Y-m-d 23:59:59", strtotime("-2 weeks monday"));
						if($row['tx_instant']>$start){
							return 3;
						}
					}
				}
				// lock on Sundays at noon
				$dow = date('N');
				$hour = date('G');
				if($dow == 7 AND $hour>=12) {
					return 3;
				}
			}
			// locked if recently dropped
			else {
				$dow = date('N');
				$hour = date('G');
				if($dow<3 OR ($dow==3 AND $hour<6) OR $dow==7 OR ($dow==6 AND $hour>6)) {
					$start = date("Y-m-d 07:00:00", strtotime("last Saturday"));
				}
				else {
					$start = date("Y-m-d 07:00:00", strtotime("last Tuesday"));
				}
				$sql = "SELECT tx_instant FROM prd_postedtx WHERE tx_player_id=$pid AND tx_type=5 ORDER BY tx_id DESC LIMIT 1";
				$retval = mysqli_query($conn,$sql);
				while($row = mysqli_fetch_array($retval)){
					if($row['tx_instant']>$start){
						return 4;
					}
				}
			}
		}
		
		return 0;
	}
	
	/*
	*
	ROSTER DISPLAY
	* conn - SQL connection variable
	* team_id - team id
	* ir - IR only
	* ps - PS only
	* my_team - my team id
	*
	*/
	function roster_display($conn,$team_id,$ir,$ps,$my_team){
			$week = $_SESSION['week'];
			if ($ir == 1) {
				$sWhere = "WHERE player_fantasyteam_ID=$team_id AND player_injuredreserve=1";
			}
			elseif ($ps == 1) {
				$sWhere = "WHERE player_fantasyteam_ID=$team_id AND player_practicesquad=1 AND player_injuredreserve=0";
			}
			else {
				$sWhere = "WHERE player_fantasyteam_ID=$team_id AND player_practicesquad=0 AND player_injuredreserve=0";
			}
			echo "<div class=\"table-responsive\">";
					echo "<table class=\"table table-bordered table-hover table-striped\">";
					echo "<thead><tr>
						<th>Player</th>
						<th>Actions</th>
						<th>Contract</th>
						<th>Bye</th>
						<th>Pos Rank</th>
						<th>Owned</th>
					</thead>
					<tbody>";	
						$sql = "SELECT pos_rank,player_practicesquad,player_injuredreserve,player_rs,player_id,player_update_date,player_own,player_status,player_nfl_team,prd_players.player_name,player_pos,player_NFL_IR,player_contract_type,player_currentsalary,player_bye 
						FROM prd_players " .
						$sWhere . 
						" ORDER BY CASE WHEN player_pos='QB' THEN '1' WHEN player_pos='RB' THEN '2' WHEN player_pos='WR' THEN '3' WHEN player_pos='TE' THEN '4' WHEN player_pos='DST' THEN '5' END ASC,player_currentsalary DESC";
						$retval = mysqli_query($conn,$sql);
						if (!$retval) {
							printf("Error: %s\n", mysqli_error($conn));
							exit();
						}
						while($row = mysqli_fetch_array($retval)){
							$player_id = $row['player_id'];
							echo "<tr>";
							echo "<td><a href=\"player_detail.php?pid=$player_id\" style=\"text-decoration : none; color : #000000;\">" . $row['player_pos'] . " <strong>" . $row['player_name'] . "</strong> " .  $row['player_nfl_team'];
							if(!empty($row['player_status']) AND $row['player_status']!="ACT" AND $row['player_status']!="Active") {
								echo  " <span class=\"red-custom\"><i>" . $row['player_status']  . "</span></i>";
							}
							if(!empty($row['player_rs']) AND $row['player_rs']==1) {
								echo "<span class=\"red-custom\"><b> (Redshirted)</span></b>";
							}
							elseif(redshirt($conn,$player_id)==1){
								echo "<span class=\"red-custom\"><i> (0Y)</span></i>";
							}
							echo "</a></td>";
							echo "<td>" . tx_buttons($conn,$player_id,$my_team,$row['player_practicesquad'],$row['player_injuredreserve'],$row['player_NFL_IR'],$team_id,$row['player_update_date'],$row['player_pos'],$row['player_rs']) . "</td>";
							echo "<td>" . "$" . $row['player_currentsalary'] . " (" . $row['player_contract_type'] . ")" . "</td>";
							if($row['player_bye'] == -1) {
								echo "<td>n/a</td>";
							}
							else{
								echo "<td>" . $row['player_bye'] . "</td>";
							}
							echo "<td>" . $row['pos_rank'] . "</td>";
							echo "<td>" . $row['player_own'] . "%</td>";
							echo "</tr>";
						}
					echo 
					"</tbody>
			   </table>
			</div>"; 
	}


	/*
	*
	W/L CALCULATIONS
	* conn - SQL connection variable
	* tid - team id
	* year - year for record
	* ret - 0 for wins, 1 for losses, 2 for finish
	*
	*/
	function result_calc($conn,$tid,$year,$ret){
		if($year == $_SESSION['year'] AND $_SESSION['period']<2){
			$week = $_SESSION['week']-1;
		}
		elseif($ret==0 OR $ret==1){
			$week = 12;
		}
		// needed to set this to 100 to account for NFL adding a game in 2021 (and prevent future additions from messing this up)
		else{
			$week = 100;
		}
		$sql = "SELECT stand_w,stand_l,stand_finish FROM prd_rollingstandings WHERE stand_year=$year AND stand_week=$week AND stand_team=$tid";
		$retval = mysqli_query($conn,$sql);
		if (!$retval) {
			printf("Error: %s\n", mysqli_error($conn));
			exit();
		}
		if(mysqli_num_rows($retval) == 0){
			return;
		}
		while($row = mysqli_fetch_array($retval)){
			if($ret==0){
				return $row['stand_w'];
			}
			elseif($ret==1){
				return $row['stand_l'];
			}
			elseif($ret==2){
				return $row['stand_finish'];
			}
		}
	}


	/*
	*
	SALARY CAP CALCULATIONS
	* conn - SQL connection variable
	* tid - team id
	* year - salary cap year
	* ar - 1 if getting active roster salary
	* ps - 1 if getting practice squad salary
	* other - 1 if getting other (ie. cut players, traded cap) salary
	* total - 1 if getting total salary
	* 
	*/
	function cap_calc($conn,$tid,$year,$ar,$ps,$other,$total){
		$ar_sal = 0;
		$ps_sal = 0;
		$other_sal = 0;
		$sql = "SELECT player_practicesquad,player_injuredreserve,player_currentsalary
		FROM prd_players 
		WHERE player_fantasyteam_id=$tid AND pbit IS NULL";
		$retval = mysqli_query($conn, $sql);
		while($row = mysqli_fetch_array($retval)){
			if($row['player_practicesquad']!=1 AND $row['player_injuredreserve']!=1) {
				$ar_sal = $ar_sal + $row['player_currentsalary'];
			}
			elseif($row['player_practicesquad']==1 AND $row['player_injuredreserve']!=1) {
				$ps_sal = $ps_sal + $row['player_currentsalary'];
			}
		}
		if($ar==1){
			return $ar_sal;
		}
		elseif($ps==1){
			return $ps_sal;
		}
		elseif($other==1){
			return (cap_hit($conn,$tid,0) - $ar_sal - $ps_sal);
		}
		elseif($total==1){
			return cap_hit($conn,$tid,0);
		}
	}

	/*
	*
	WATCHLIST UPDATES
	* conn - SQL connection variable
	* pid - player id
	* tid - team id
	* remove - 1 if removing from wl, 0 if adding
	*
	*/
	function watchlist($conn,$pid,$tid,$remove) {
		if($remove==0){
			$sql = "SELECT wl_player_id FROM prd_watchlist WHERE wl_player_id=$pid AND wl_fantasyteam_id=$tid";
			$retval = mysqli_query($conn,$sql);
			if(mysqli_num_rows($retval)==0) {
				$sql = "INSERT INTO prd_watchlist (wl_player_id,wl_fantasyteam_id) VALUES ($pid,$tid)";
				$retval = mysqli_query($conn,$sql);
				echo "<div class=\"alert alert-success\">";
				echo "Added to Watchlist! <a href=\"rosters_new.php?tid=$tid&wl=1\">Go to My Watchlist</a>";
				echo "</div>";
			}
		}
		elseif($remove==1){
			$sql = "DELETE FROM prd_watchlist WHERE wl_player_id=$pid AND wl_fantasyteam_id=$tid";
			$retval = mysqli_query($conn,$sql);
			echo "<div class=\"alert alert-danger\">";
			echo "Removed from Watchlist. <a href=\"rosters_new.php?tid=$tid&wl=1\">Go to My Watchlist</a>";
			echo "</div>";
		}
	}
	
	/* 
	*
	TRADE BLOCK UPDATES
	* conn - SQL connection variable
	* pid - player id (0 if updating pick or note)
	* pickid - draft pick id (0 if updating player or note)
	* tid - team id
	* remove - 1 if removing from trade block, 0 if adding or updating note
	* text_update - 1 if updating text
	* text - text to save as note
	*
	*/
	function trade_block($conn,$pid,$pickid,$tid,$remove,$text_update,$text) {
		if($remove==1){
			if($pid > 0){
				$sql = "DELETE FROM prd_tradeblock WHERE tb_player_id=$pid AND tb_team_id=$tid";
				$retval = mysqli_query($conn,$sql);
			}
			elseif($pickid > 0){
				$sql = "DELETE FROM prd_tradeblock WHERE tb_pick_id=$pickid AND tb_team_id=$tid";
				$retval = mysqli_query($conn,$sql);
			}
		}
		elseif($remove==0 AND $text_update==0){
			if($pid > 0){
				$sql = "SELECT tb_player_id FROM prd_tradeblock WHERE tb_player_id=$pid AND tb_team_id=$tid";
				$retval = mysqli_query($conn,$sql);
				if(mysqli_num_rows($retval)==0) {
					$sql = "INSERT INTO prd_tradeblock (tb_team_id,tb_player_id,tb_update_date) VALUES ($tid,$pid,CURRENT_TIMESTAMP)";
					$retval = mysqli_query($conn,$sql);
				}
			}
			elseif($pickid > 0){
				$sql = "SELECT tb_pick_id FROM prd_tradeblock WHERE tb_pick_id=$pickid AND tb_team_id=$tid";
				$retval = mysqli_query($conn,$sql);
				$count = mysqli_num_rows($retval);
				if($count==0) {
					$sql = "INSERT INTO prd_tradeblock (tb_team_id,tb_pick_id,tb_update_date) VALUES ($tid,$pickid,CURRENT_TIMESTAMP)";
					$retval = mysqli_query($conn,$sql);
				}
			}
		}
		elseif($text_update==1){
			$sql = "SELECT tb_notes FROM prd_tradeblock WHERE tb_team_id=$tid AND tb_notes IS NOT NULL";
			$retval = mysqli_query($conn,$sql);
			if(mysqli_num_rows($retval)==0) {
				$sql = "INSERT INTO prd_tradeblock (tb_team_id,tb_notes) VALUES ($tid,'$text')";
				$retval = mysqli_query($conn,$sql);
			}
			else {
				$sql = "UPDATE prd_tradeblock SET tb_notes='$text' WHERE tb_team_id=$tid AND tb_player_id IS NULL";
				$retval = mysqli_query($conn,$sql);
			}
			echo "<div class=\"alert alert-success\">";
			echo "Notes Saved!";
			echo "</div>";
		}
	}
	/*
	*
	RED SHIRT ELIGIBLE?
	* conn - SQL connection variable
	* pid - player_id
	* RETURNS: 1 if eligible, 0 if not
	*/
	function redshirt($conn,$pid){
		$year = $_SESSION['year'];
		$last_rs = date("Y-m-d H:i:s", strtotime('-18 months'));
		
		$sql = "SELECT player_rs FROM prd_players WHERE player_id=$pid";
		$retval = mysqli_query($conn, $sql); 
		while($row = mysqli_fetch_array($retval)) {
			if(!empty($row['player_rs']==1) AND $row['player_rs']==1) {
				return 1;
			}
		}
		
		// has player been redshirted before?
		$sql = "SELECT COUNT(tx_id) as tx_count FROM prd_postedtx WHERE tx_type=12 AND tx_instant>'$last_rs' AND tx_player_id=$pid";
		$retval = mysqli_query($conn,$sql);
		while($row = mysqli_fetch_array($retval)){
			if($row['tx_count'] > 0){
				return 0;
			}
		}
		
		// get date of first game (player needs to be on PS at this point to be RS'd) 
		$sql = "SELECT first_game FROM prd_weeks WHERE year=$year AND week=1";
		$retval = mysqli_query($conn,$sql);
		while($row = mysqli_fetch_array($retval)){
			$deadline = $row['first_game'];
		}
		
		$sql = "SELECT COUNT(tx_id) as tx_count FROM prd_postedtx WHERE tx_type<6 AND tx_instant>'$deadline' AND tx_player_id=$pid";
		$retval = mysqli_query($conn,$sql);
		while($row = mysqli_fetch_array($retval)){
			if($row['tx_count'] > 0){
				return 0;
			}
		}
		
		$sql = "SELECT player_contract_type,player_practicesquad FROM prd_players WHERE player_id=$pid";
		$retval = mysqli_query($conn,$sql);
		while($row = mysqli_fetch_array($retval)){
			if($row['player_contract_type']!="ABo"){
				return 0;
			}
			if($row['player_practicesquad']!=1){
				return 0;
			}
		}
		
		return 1;
	}
	
	/*
	*
	TX BUTTONS
	* conn - SQL connection variable
	* pid - player_id
	* tid - user's team
	*/
	function tx_buttons($conn,$pid,$tid,$ps,$ir,$nfl_ir,$pteam,$deadline,$pos,$rs){
		
		if($tid == 0){
			return "Log in";
		}
		$buttons = "";
		
		$wl = 0;
		$sql = "SELECT * FROM prd_watchlist WHERE wl_player_id=$pid AND wl_fantasyteam_id=$tid";
		$retval = mysqli_query($conn,$sql);
		if(mysqli_num_rows($retval)>0){
			$wl = 1;
		}
		
		// if FA draft ongoing, can't demote drafted player
		$drafted = 0;
		$year = $_SESSION['year'];
		$sql = "SELECT * FROM prd_fadraft WHERE pick_active=1";
		$retval = mysqli_query($conn,$sql);
		if(mysqli_num_rows($retval)>0){
			$sql2 = "SELECT * FROM prd_fadraft WHERE pick_player=$pid AND pick_year=$year";
			$retval2 = mysqli_query($conn,$sql2);
			if(mysqli_num_rows($retval2)>0){
				$drafted = 1;
			}
		}
		
		$locked = is_locked($conn,$pid,$deadline); 
		if($tid == $pteam) {
			if($ps == 1){
				if($locked==0){
					$buttons = $buttons . "<a button type=\"button\" href=\"/internal_prd.php?promote=$pid\" class=\"btn btn-xs btn-success\" style=\"margin-left:2px\"><i class=\"glyphicon glyphicon-arrow-up\"></i></button></a>";
				}
			}
			elseif($ir == 0 AND $pos != "DST" AND $drafted==0){
				if($locked == 0){
					$buttons = $buttons . "<a button type=\"button\" href=\"/internal_prd.php?demote=$pid\" class=\"btn btn-xs btn-warning\" style=\"margin-left:2px\"><i class=\"glyphicon glyphicon-arrow-down\"></i></button></a>";
				}
			}
			
			if($ir == 1 AND $locked != 1){
				$buttons = $buttons . "<a button type=\"button\" href=\"/internal_prd.php?activate=$pid\" class=\"btn btn-xs btn-success\" style=\"margin-left:2px\"><i class=\"glyphicon glyphicon-star\"></i></button></a>";
			}
			elseif(($locked == 0 OR $locked == 2) AND $nfl_ir == 1 AND $pos != "DST"){
				$buttons = $buttons . "<a button type=\"button\" href=\"/internal_prd.php?inactivate=$pid\" class=\"btn btn-xs btn-danger\" style=\"margin-left:2px\"><i class=\"glyphicon glyphicon-bed\"></i></button></a>";
			}
			
			if($locked!=1 AND $ir == 0 AND $_SESSION['period']!=3){
				$buttons = $buttons . "<a button type=\"button\" href=\"/internal_prd.php?drop=$pid\" class=\"btn btn-xs btn-danger\" style=\"margin-left:2px\"><i class=\"glyphicon glyphicon-remove\"></i></button></a>";
			}
			
			if($locked!=1 AND ($_SESSION['period']!=1 AND $_SESSION['period']!=2) AND $ir == 0){
				$buttons = $buttons . "<a button type=\"button\" href=\"/trades_new.php?pid=$pid\" class=\"btn btn-xs btn-info\" style=\"margin-left:2px\"><i class=\"glyphicon glyphicon-transfer\"></i></button></a>";
			}
			
			$sql = "SELECT * FROM prd_tradeblock WHERE tb_player_id=$pid AND tb_team_id=$tid";
			$retval = mysqli_query($conn,$sql);
			if(mysqli_num_rows($retval)==0){
				$buttons = $buttons . "<button type=\"submit\" name=\"add_tb\" value=$pid class=\"btn btn-xs btn-default\" style=\"margin-left:2px\"><i class=\"glyphicon glyphicon-tag\"></i></button></a>";											
			}
			else{
				$buttons = $buttons . "<button type=\"submit\" name=\"rem_tb\" value=$pid class=\"btn btn-xs btn-danger\" style=\"margin-left:2px\"><i class=\"glyphicon glyphicon-minus\"></i></button></a>";																													
			}
			
		}
		elseif($pteam > 0){
			if($locked != 1){
				if($rs == 0 AND $ps == 1 AND $ir == 0 AND $_SESSION['period']==1){
					$buttons = $buttons . "<a button type=\"button\" href=\"/add_prd.php?add=$pid&ps=1\" class=\"btn btn-xs btn-success\" style=\"margin-left:2px\"><i class=\"glyphicon glyphicon-plus\"></i></button></a>";																			
				}
				if($_SESSION['period']!=1 AND $_SESSION['period']!=2){
					$buttons = $buttons . "<a button type=\"button\" href=\"/trades_new.php?pid=$pid&tid=$pteam\" class=\"btn btn-xs btn-success\" style=\"margin-left:2px\"><i class=\"glyphicon glyphicon-transfer\"></i></button></a>";
				}
			}
			if($wl == 0){	
				$buttons = $buttons . "<button type=\"submit\" name=\"add_wl\" value=$pid class=\"btn btn-xs btn-info\" style=\"margin-left:2px\"><i class=\"glyphicon glyphicon-eye-open\"></i></button></a>";	
			}	
			else{
				$buttons = $buttons . "<button type=\"submit\" name=\"delete_wl\" value=$pid class=\"btn btn-xs btn-light\" style=\"margin-left:2px\"><i class=\"glyphicon glyphicon-eye-close\"></i></button></a>";	
			}
		}
		else{
			if($locked == 0 AND ($_SESSION['period']==1 OR $_SESSION['period']==2) AND $locked!=4){
				$buttons = $buttons . "<a button type=\"button\" href=\"/add_prd.php?add=$pid\" class=\"btn btn-xs btn-success\" style=\"margin-left:2px\"><i class=\"glyphicon glyphicon-plus\"></i></button></a>";												
			}
			if($wl == 0){	
				$buttons = $buttons . "<button type=\"submit\" name=\"add_wl\" value=$pid class=\"btn btn-xs btn-info\" style=\"margin-left:2px\"><i class=\"glyphicon glyphicon-eye-open\"></i></button></a>";	
			}	
			else{
				$buttons = $buttons . "<button type=\"submit\" name=\"delete_wl\" value=$pid class=\"btn btn-xs btn-light\" style=\"margin-left:2px\"><i class=\"glyphicon glyphicon-eye-close\"></i></button></a>";	
			}		}
		return $buttons;
	}
	
	/*
	*
	ROSTER MOVE TABLE
	* conn - SQL connection variable
	* team_id - team ID
	* col - columns to include, in order(#s correspond to z_txtypes)
	* BROKE THIS TO MAKE TX_BUTTONS WORK WITH AJAX - NEED TO FIGURE OUT JAVASCRIPT FOR DROP[], etc.
	*/
	function roster_move_table($conn,$team_id,$col){
		echo
			"<div class=\"table-responsive\">
				<table class=\"table table-bordered table-hover\">
					<thead>
						<tr>
							<th>Player</th>";
							for($i=0;$i<count($col);$i++){
								if($col[$i] == 1){
									echo "<th><span class=\"glyphicon glyphicon-arrow-up\"></th>";
								}
								elseif($col[$i] == 2){
									echo "<th><span class=\"glyphicon glyphicon-arrow-down\"></th>";
								}
								elseif($col[$i] == 5){
									echo "<th><span class=\"glyphicon glyphicon-arrow-remove\"></th>";
								}
								elseif($col[$i] == 6){
									echo "<th><span class=\"glyphicon glyphicon-arrow-transfer\"></th>";
								}
								elseif($col[$i] == 7){
									echo "<th><span class=\"glyphicon glyphicon-arrow-heart\"></th>";
								}
								elseif($col[$i] == 8){
									echo "<th><span class=\"glyphicon glyphicon-arrow-heart-empty\"></th>";
								}
							}
							echo "<th>Contract</th>
						</tr>
					</thead>
					<tbody>";
					$sql = "SELECT player_id,player_name,player_pos,player_injuredreserve,player_practicesquad,player_contract_type,player_nfl_team,player_status,player_currentsalary,player_NFL_IR,player_update_date FROM prd_players LEFT JOIN nfl_schedule ON player_nfl_team=TEAM WHERE player_fantasyteam_ID=$team_id AND player_injuredreserve=0 ORDER BY CASE WHEN player_pos='QB' THEN '1' WHEN player_pos='RB' THEN '2' WHEN player_pos='WR' THEN '3' WHEN player_pos='TE' THEN '4' WHEN player_pos='DST' THEN '5' END ASC,player_currentsalary DESC";
					$retval = mysqli_query($conn, $sql );
					while($row = mysqli_fetch_array($retval)){
						$player_id = $row['player_id'];
						$update_date = $row['player_update_date'];
						if($row['player_practicesquad']==1){
							echo "<tr class=\"foo2\">";
						}
						elseif($row['player_injuredreserve']==1){
							echo "<tr class=\"active\">";
						}
						else{
							echo "<tr>";
						}
						if(!empty($row['player_status']) AND $row['player_status']!="ACT" AND $row['player_status']!="Active") {
							echo "<td><a href=\"player_detail.php?pid=$player_id\" style=\"text-decoration : none; color : #000000;\">" . $row['player_pos'] . " <strong>" . $row['player_name'] . "</strong> " .  $row['player_nfl_team'] .  " <span class=\"red-custom\"><i>" . $row['player_status']  . "</span></i>" .  "</a></td>";
						}
						else {
							echo "<td><a href=\"player_detail.php?pid=$player_id\" style=\"text-decoration : none; color : #000000;\">" . $row['player_pos'] . " <strong>" .  $row['player_name'] . "</strong> " .  $row['player_nfl_team'] . "</a></td>";
						}
						for($i=0;$i<count($col);$i++){
							if($col[$i] == 1){
								if(!empty($_GET['promote']) AND $row['player_id']==$_GET['promote']) {
									echo "<td>" . "<input type=\"checkbox\" name=\"promote[]\" value=$player_id checked>" . "</td>";
								}
								elseif($row['player_practicesquad']==1 AND $row['player_injuredreserve']==0 AND is_locked($conn,$player_id,$update_date)!=2) {
									echo "<td>" . "<input type=\"checkbox\" name=\"promote[]\" value=$player_id>" . "</td>";
								}
								else {
									echo "<td></td>";
								}
							}
							elseif($col[$i] == 2){
								// demote
								if(is_locked($conn,$player_id,$update_date)==1 OR is_locked($conn,$player_id,$update_date)==3){
									echo "<td></td>";
								}
								elseif($row['player_practicesquad']==1){
									echo "<td></td>";
								}
								else {
									echo "<td>" . "<input type=\"checkbox\" name=\"demote[]\" value=$player_id>" . "</td>";
								}
							}
							elseif($col[$i] == 5){
								// drop
								if(is_locked($conn,$player_id,$update_date)==1){
									echo "<td></td>";
								}
								else {
									echo "<td>" . "<input type=\"checkbox\" name=\"drop[]\" value=$player_id>" . "</td>";
								}
							}
							elseif($col[$i] == 6){
								echo "<th><span class=\"glyphicon glyphicon-arrow-transfer\"></th>";
							}
							elseif($col[$i] == 7){
								echo "<th><span class=\"glyphicon glyphicon-arrow-heart\"></th>";
							}
							elseif($col[$i] == 8){
								echo "<th><span class=\"glyphicon glyphicon-arrow-heart-empty\"></th>";
							}
						}
						
						// contract
						echo "<td>" . "$" . $row['player_currentsalary'] . " (" . $row['player_contract_type'] . ")" . "</td>";
						echo "</tr>";
					}
					echo
					"</tbody>
				</table>
				</div>";
		
	}
	
	/*
	*
	NO MATCH
	* conn - SQL connection variable
	* team_id - team ID giving up player
	* pid - claimed player
	*/
	function no_match($conn,$team_id,$pid) {
		$dropped = 0;
		$demoted = 0;
		$sql = "SELECT * FROM prd_psclaims LEFT JOIN prd_players ON tx_claimedplayer_id=player_id WHERE tx_claimedplayer_id=$pid AND tx_completed=0 AND tx_won=1";
		$retval = mysqli_query($conn,$sql);
		while($row = mysqli_fetch_array($retval)){
			$bid = $row['tx_bid'];
			$tid = $row['tx_claimteam_id'];
			if($row['tx_droppedplayer_id']>0){
				$dropped = $row['tx_droppedplayer_id'];
				$dropped_sal = $row['tx_dropped_sal'];
			}
			if($row['tx_demotedplayer_id']>0){
				$demoted = $row['tx_demotedplayer_id'];
			}
			$yrs = contract_terms($conn,$row['player_contract_type'],0,3);
		}
		
		
		$sql = "UPDATE prd_psclaims SET tx_added=1,tx_completed=1 WHERE tx_claimedplayer_id=$pid AND tx_completed=0 AND tx_won=1";
		$retval = mysqli_query($conn,$sql);
		
		// cover edge case where someone sneaks a claim in during the period after the initial claim is won but before the promotion/cut decision happens
		$sql = "UPDATE prd_psclaims SET tx_completed=1,tx_noadd_reason=\"Claimed player already won in previous claim\" WHERE tx_claimedplayer_id=$pid AND tx_completed=0";
		$retval = mysqli_query($conn,$sql);
		
		$sql = "INSERT INTO prd_postedtx (tx_instant,tx_type,tx_player_id,tx_originalteam_id,tx_newteam_id,tx_newteam_salary) VALUES (now(),4,$pid,$team_id,$tid,$bid)";
		$retval = mysqli_query($conn,$sql);
		
		$sql = "UPDATE prd_players SET player_fantasyteam_ID=$tid,player_practicesquad=0,player_currentsalary=$bid,player_update_date=now(),pbit=NULL WHERE player_id=$pid";
		$retval = mysqli_query($conn,$sql);

		if($demoted > 0){
			roster_px($conn,$tid,$demoted,0,2,"","");
		}
		
		if($dropped > 0) {
			$sal_array = explode(",",$dropped_sal);
			roster_px($conn,$tid,$dropped,0,5,"",$sal_array);
		}
	}
	
	/*
	*
	CONTRACT TERMS
	* conn - SQL connection variable
	* type - current contract type
	* add - years to add to contract type
	* opt - type of processing to do (1 - increment contract, 2 - new extension, 3 - get years remaining)
	*/
	function contract_terms($conn,$type,$add,$opt){
		$len = strlen($type);
		if($opt==1){
			if($len==2){
				$first = substr($type,0,1);
				$second = substr($type,1,1);
				$first++;
				$new_type = $first . $second;
				return $new_type;
			}
			elseif($type=="ABo"){
				return "BBo";
			}
		}
		elseif($opt==2){
			if($type=="BBo" AND $add==1){
				return "CC";
			}
			elseif($type=="AoBo" AND $add==1) {
				return "BBo";
			}
			elseif($type=="Tag #1"){
				return "Tag #2";
			}
			elseif($type=="Tag #2"){
				return "Tag #3";
			}
			elseif($len==2 AND substr($type,0,1)==substr($type,1,1)){
				return "Tag #1";
			}
			$first = "A";
			for($i=1;$i<$add;$i++){
				$first++;
			}
			$new_type = "A" . $first;
			return $new_type;
		}
		elseif($opt==3){
			if($len==2){
				$first = substr($type,0,1);
				$second = substr($type,1,1);
				return (ord($second)-ord($first)+1);
			}
			elseif($type=="ABo"){
				return 2;
			}
			else {
				return 1;
			}
		}
	}
	/*
	*
	ADD PLAYER TO IR
	* conn - SQL connection variable
	* pid - player id
	* add - 0 if remove, 1 if add
	*/
	function add_to_ir($conn,$pid,$add){
		if($add == 0) {
			$sql = "UPDATE prd_players SET player_NFL_IR=0 WHERE player_id=$pid";
			$retval = mysqli_query($conn,$sql);
		}
		elseif($add == 1) {
			$sql = "UPDATE prd_players SET player_NFL_IR=1 WHERE player_id=$pid";
			$retval = mysqli_query($conn,$sql);
		}
	}
	
?>

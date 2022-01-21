<?php

$SMTP = "mail.local";

/* connect to server */
$hostname = '{'.$SMTP.':993/imap/ssl/novalidate-cert}INBOX';
$username = 'emample@local';
$password = 'password';

/* try to connect */
$inbox = imap_open($hostname,$username,$password) or die('Cannot connect to email: ' . imap_last_error());

/* grab emails */
//$emails = imap_search($inbox,'UNSEEN');
$emails = imap_search($inbox,'ALL');

/* if emails are returned, cycle through each... */
if($emails) {

//connect to sql
include("db.php");
	
	/* put the newest emails on top */
	//rsort($emails);
	
	/* for every email... */
	foreach($emails as $email_number) {
	$TID = '';
	$UID = '';
	
		/* get information specific to this email */
		$overview = imap_fetch_overview($inbox,$email_number,0);
		$structure = imap_fetchstructure($inbox, $email_number);
        $message = imap_fetchbody($inbox,$email_number,1);

			//decode message- depending on type
			if($structure->encoding == 3) {
                $message = imap_base64($message);
            } else if($structure->encoding == 1) {
                $message = imap_8bit($message);
            } else {
                //$message1 = imap_qprint($message); - changed from this to bwlow script.
				$message = preg_replace("/\=([A-F][A-F0-9])/","%$1",$message);
				$message = urldecode($message);
				
            } //end decode
			
		//$overview[0]->from
		//$overview[0]->subject;
		//$overview[0]->udate
		//var_dump($structure);
		//var_dump($overview[0]);

		//split from and extract email address to check
		preg_match("'<(.*?)>'si", $overview[0]->from, $FromStrip);
		//echo $FromStrip[1];
		
		//check for sender in tech list to get username.
		$result=mysqli_query($db,"SELECT UID FROM technicians WHERE EMAIL='$FromStrip[1]'");
		$count=mysqli_num_rows($result);
		$row=mysqli_fetch_array($result,MYSQLI_ASSOC);
			if($count==1)
			{
				$UID = $row['UID'];
			}else{				
				//email to say UID not found in db
				echo "UID not Found in SQL: " . $UID;	
			} //end count sql				
		mysqli_free_result($result);				

		//if UID is found in SQL.
		if ($UID !== ''){
			
			//check for TID tag in email. and get value
			preg_match("'{TID}(.*?){-TID}'si", $message, $match);
			if($match){
				//search for TID in SQL-

				//check if valid TID and  from sql
				$result=mysqli_query($db,"SELECT * FROM tickets WHERE TID='$match[1]'");
				$count=mysqli_num_rows($result);
				$row=mysqli_fetch_array($result,MYSQLI_ASSOC);
					if($count==1)
					{
						$TID = $row['TID'];
						$STATUS = $row['STATUS'];
						$DENYSTATUS = $row['DENYSTATUS'];
						$DENYTECH = $row['DENYTECH'];
						$TECHEMAILED = $row['TECHEMAILED'];
						$REASSIGNED = $row['REASSIGNED'];
						$EMAILTIME = $row['EMAILTIME'];
						//echo $TID;
						//echo $STATUS;
					}else{
						//email to say TID not found in db- this could mean that they are replying to a non existant ticket.
						echo "TID not Found in SQL: " . $TID;
					} //end count sql				
				mysqli_free_result($result);			
			
				if ($TID !== ''){
		
					//check for REPLY tag in email. and get value
					preg_match("'{KEEPER}(.*?){-KEEPER}'si", $message, $match);
					//var_dump($match);
					
					if($match){
						if ($EMAILTIME == $match[1]){
						
							//check for REPLY tag in email. and get value
							preg_match("'{REPLY}(.*?){-REPLY}'si", $message, $match);
							//var_dump($match);
							
							if($match){
								
								//if reassigned is 0 then continue otherwise skip other people from submiting - if errors etc.
								if ($REASSIGNED == 0){

									//if reply was in email-then see if it is accepted or denied
									if ($match[1] == "ACCEPT"){
										//here we have to increase status to 1 meaning in progress- and stop other people from accepting the job. - 
										if ($STATUS == '0'){
											//technician gets the job- and status increases to 1 so if other people try to accept it will deny them.
											
											//email user with add note button, close button-
											//if(mail($to,$subject,$message,$headers)){
											//	echo "Check your email now....<BR/>";		
											//}else{
											//	//email me and let me know there is an error.
											//	echo "Error: Server did not send email.";
											//} //end send mail	

											//increase SQL status to 1
											//adds technician of taken ticket to SQL
											//adds time technician accepted to ticket
											$sql = "UPDATE tickets SET STATUS='1', TIMEASSIGNED='".$overview[0]->udate."', USRACCEPT='$UID' WHERE TID='$TID'";
											if (mysqli_query($db, $sql)) {
												echo "technician who taken ticket, time technician accepted & set status to 1 - Completed successfully";
											} else {
												//email and let me know there was an error
												echo "Error updating record(s): " . mysqli_error($db);
											}						
											
										}else{
										//ticket already taken	email user and let then know.
										echo "ticket already taken";
										} //end if STATUS check
										
										
									} //end if REPLY tag is ACCEPT
									if ($match[1] == "DENY"){
										
										if ($STATUS == '0'){
											
											//if user has denied the ticket but status is still 0 then add to counter.
											//we need to get the current DENYSTATUS and check that against the amount of technicians that received the emailed ticket.
											//we can do this above when getting the ticket
											
											//here we must add the technician to the denied list, but first check if he is currently in there.
											
											//separate the row - ;peterg:43433434 -username:time denied
											$separated = explode(";", $DENYTECH);
											$DenyCount = 0;
											foreach($separated as $value)
											{
											$separatedUID = explode(":", $value);	
												if ($separatedUID[0] == $UID){
													//email technician and let them know they cant mail more than once
													echo "you cant mail more than once.";
													$DenyCount = 1;
													//$separatedUID[1];
												}
											} //end foreach
											
											if ($DenyCount == 0){
												$DENYSTATUS = $DENYSTATUS + 1;
												$DENYTECH = $DENYTECH . ";" . $UID . ":" . $overview[0]->udate;
												$sql = "UPDATE tickets SET DENYSTATUS='$DENYSTATUS',DENYTECH='$DENYTECH' WHERE TID='$TID'";
												if (mysqli_query($db, $sql)) {
													echo "deny status and denytech completed successfully";
												} else {
													//email and let me know there was an error
													echo "Error updating record(s): " . mysqli_error($db);
												}
												
												//check if denystatus and techemailed is equal, if so then email manager.
												if ($DENYSTATUS >= $TECHEMAILED){
													echo "denystatus is the same as techemailed- call needs attention.";
												}
											} //end if DenyCount is 0 ;ok
										}else{
										
										echo "status is " . $STATUS;
										
										//email user with rejected email to say call is already taken.
										//if(mail($to,$subject,$message,$headers)){
										//	echo "Check your email now....<BR/>";		
										//}else{
										//	//email me and let me know there is an error.
										//	echo "Error: Server did not send email.";
										//} //end send mail		
											
										}
										
									} //end if REPLY tag is DENY
									
									if ($match[1] == "RETURN"){
										
										if ($STATUS == '0'){

											//if return then isreassigned is activated and manager is emailed and call put in que.
											//if(mail($to,$subject,$message,$headers)){
											//	echo "Check your email now....<BR/>";		
											//}else{
											//	//email me and let me know there is an error.
											//	echo "Error: Server did not send email.";
											//} //end send mail		
										
										
											$DENYTECH = $DENYTECH . ";" . $UID . "-RTN:" . $overview[0]->udate;							
											$sql = "UPDATE tickets SET REASSIGNED='1',DENYTECH='$DENYTECH' WHERE TID='$TID'";
											if (mysqli_query($db, $sql)) {
												echo "reassigned and denytech completed successfully";
											} else {
												//email and let me know there was an error
												echo "Error updating record(s): " . mysqli_error($db);
											}							
											
										}else{
											//email to say job is already taken	
											echo "ticket has been taken";
										}							
										
									} //end if REPLY tag is RETURN		

									if ($match[1] == "ADDNOTE"){
										
										//if reply is addnote then check if ticket is open - * and tech assigned is updating the call.
										if ($STATUS == '1'){
											
											//add note here to new SQL table linked with message,ID,timenow,technican,PRIMARY
											
										}else{
											//if status is not assigned or closed then email to say.
										}
										
									} //end if REPLY tag is ADDNOTE	

									if ($match[1] == "CLOSE"){
										
										//
										if ($STATUS == '1'){
											
											//we need to add a closed time to database? and increas status to 3 //closed
											
										}
										
									} //end if REPLY tag is CLOSE	
									
								}else{
									//email user to say that ticked has been reassigned
									echo "ticked has been reassigned and on hold.";
								} // end if REASSIGNED == 1
							}else{
								//email the error; and log
								echo "ERROR -REPLY NOTHING FOUND";
							} //end if matched
						
						}else{
							echo "EMAILTIME is not the same so technican is replying to old call before being reassigned.";
						} //end EMAILTIME is not the same
						
					}else{
						echo "KEEPER not found in email";
					} //end if $EMAILTIME is found in email
				} //end TID is found in DB	
			
			}else{
				//email the error; and log
				echo "ERROR TID- NOTHING FOUND";
			} //end TID search

		} //end UID is found in DB	
		
	} //end looping through emails

//close connection
mysqli_close($db);
} //end if any emails returned. //log here?

/* close the connection */
imap_close($inbox);

//echo "<a href='mailto:trackthis@local?subject=Re:%20-%20unsatisfied&body=messagethisis'> Click here if your issue was not properly resolved</a>";


?>
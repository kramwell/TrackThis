<?php

$FROM = "trackthis@mail.local";
$SMTP = "mail.local";
$TicketPreFix = "KWT";

//this file would be for submitting the info to sql and emailing the technicians with the accept/reject email.

$NAME = "Joe Bloggs";
$TELNO = "555444555";
$EMAIL = "joe.bloggs@local";
$MESSAGE = "leak in main hospital corridor think tap?";
$SHORT = "leak in corridor";
$DEPARTMENT = "Plummers";


include("db.php");


	
	//email technicians and let them know.
	
	//find department based on department posted, then email them
	$sql = "SELECT EMAIL FROM technicians WHERE DEPARTMENT = '$DEPARTMENT'";
	$result = mysqli_query($db, $sql);
	$TECHEMAILED = mysqli_num_rows($result);
	if ($TECHEMAILED > 0) {
		// output data of each row
		
		//insert ticket into SQL
		$TIMESTAMP = time();
		$sql = "INSERT INTO tickets (NAME, EMAIL, TELNO, MESSAGE, DEPARTMENT, STATUS, TIMESTAMP, TECHEMAILED, EMAILTIME)
		VALUES ('$NAME', '$EMAIL', '$TELNO', '$MESSAGE', '$DEPARTMENT', '0', '$TIMESTAMP', '$TECHEMAILED', '$TIMESTAMP')";

		if (mysqli_query($db, $sql)) {
			echo "New record created successfully";
		
			$TICKET =  mysqli_insert_id($db);		//get auto increment value - mysqli_insert_id($db);
			
			
			while($row = mysqli_fetch_assoc($result)) {

				//email technicians and let then know, display ticket details.

				ini_set("SMTP", $SMTP);
				ini_set("sendmail_from", $FROM);

				$to = $row["EMAIL"];
				
				//subject = #XXX545 - leak in main building
				$subject = "[#" . $TicketPreFix . $TICKET . "] - ";
				
				$MailToBodyAccept = "You are about to accept this job.\r\nPlease send to accept.\r\n\r\n<--DO NOT EDIT BELOW THIS TEXT-->\r\n{REPLY}ACCEPT{-REPLY}\r\n{TID}".$TICKET."{-TID}\r\n{KEEPER}".$TIMESTAMP."{-KEEPER}";
				$MailToBodyDeny = "You are about to deny this job.\r\nPlease send to deny this ticket.\r\n\r\n<--DO NOT EDIT BELOW THIS TEXT-->\r\n{REPLY}DENY{-REPLY}\r\n{TID}".$TICKET."{-TID}\r\n{KEEPER}".$TIMESTAMP."{-KEEPER}";
				$MailToBodyReturn = "You are about to return this job as it is incorrectly assigned.\r\nPlease send to return this ticket.\r\n\r\n<--DO NOT EDIT BELOW THIS TEXT-->\r\n{REPLY}RETURN{-REPLY}\r\n{TID}".$TICKET."{-TID}\r\n{KEEPER}".$TIMESTAMP."{-KEEPER}";
				
				//htmlentities($body);
				$message = "
				<html>
				<head>
				<title>HTML email</title>
				</head>
				<body>
				<p>This email contains HTML Tags!</p>
				<table>
				<tr>
				<th>Firstname</th>
				<th><a href='mailto:".$FROM."?subject=".rawurlencode($subject)."DENY&amp;body=". rawurlencode($MailToBodyDeny) ."'>DENY</a></th>
				</tr>
				<tr>
				<td><a href='mailto:".$FROM."?subject=".rawurlencode($subject)."ACCEPT&amp;body=". rawurlencode($MailToBodyAccept) ."'>ACCEPT</a></td>
				<td><a href='mailto:".$FROM."?subject=".rawurlencode($subject)."RETURN&amp;body=". rawurlencode($MailToBodyReturn) ."'>RETURN</a></td>
				</tr>
				</table>
				</body>
				</html>
				";

				//set content-type and from address
				$headers = "MIME-Version: 1.0" . "\r\n";
				$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
				$headers .= 'From: <'.$FROM.'>' . "\r\n";
				
				//print_r (get_html_translation_table()); // HTML_SPECIALCHARS is default.	
				$subject = $subject . $SHORT;
				
				mail($to,$subject,$message,$headers);
				
					//if(mail($to,$subject,$message,$headers)){
					//	echo "Check your email now....<BR/>";		
					//}else{
					//	//email me and let me know there is an error.
					//	echo "Error: Server did not send email.";
					//} //end send mail	

			}  //loop users in department
			
		} else {
			//email me and let me know there is an error creating ticket.
			echo "Error: " . $sql . "<br>" . mysqli_error($db);
		}
		
	} else {
		//email me and let me know there is an error.
		echo "0 results";
	} //end loop amount of users found in given department.

mysqli_free_result($result);
mysqli_close($db);
	

?>
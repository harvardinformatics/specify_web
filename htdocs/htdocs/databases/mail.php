<?php

include_once("ReCaptcha.php");

$add_correct_addressee = "gandhi@oeb.harvard.edu";
$comment_addressee = "cms-comments@oeb.harvard.edu,huh-requests@oeb.harvard.edu";
$smtp_address = "smtp.lsdiv.harvard.edu";
$init_address = "huh_it@oeb.harvard.edu";

$reply = "Submission received.  Thank you";


$reqtype = preg_replace("/[^a-z]/","",$_POST['reqtype']);
$name = preg_replace("/[^A-Z\ a-z::alpha::\.]/","",$_POST['name']);
$replyTo = preg_replace("/[^A-Za-z0-9\.\@_\-~#]/","",$_POST['email']);
$msgtext = $_POST['usertext'];
$msgtext = trim($msgtext);

$gRecaptchaResponse = $_POST['g-recaptcha-response'];
$recaptcha = new ReCaptcha('6LcDywUTAAAAAGr4rLkqhyMyGLQdSinNH-fU4c6P');
$recaptresponse = $recaptcha->verify($gRecaptchaResponse, $_SERVER['REMOTE_ADDR']);
if ($recaptresponse->isSuccess()) {

   if ($name=="") { $name = "no name given"; }
   if ($reqtype!="add") { $reqtype = "comment"; }

   $subject = "";
   $to = "";
   if ($reqtype == "add") {
       $to = $add_correct_addressee;
       $subject = "Addition/correction submission via CMS website";
   } else {
       $to = $comment_addressee;
       $subject = "Comment submission via CMS website";
   }

   $body = "The following message was submitted to the HUH website by " . $name .". \n";

   $replyTo = $replyTo.trim();
   $replyHeader = "";
   if ($replyTo!="") {
       $body .= "Please use ". $replyTo ." to respond to the sender.\r\n\r\n";
       $replyHeader = "Reply-To: ". $replyTo . "\r\n" ;
   } else {
       $body .= "The sender did not include an email address.\r\n\r\n";
   }

   $body .= $msgtext;
   $headers = 'From: ' . $init_address  . "\r\n" .
              $replyHeader .
              'X-Mailer: PHP/' . phpversion();

   if (($msgtext!="")) {
       // uncomment the following line to test
       // $to = "bdim@oeb.harvard.edu";
       $result = mail($to,$subject,$body,$headers);
       if (!$result) {
           $reply = "Error sending message.";
       }
   } else {
       $reply = "No message content.";
   }

} else {
    $errors = $recaptresponse->getErrorCodes();
    $reply = "Error generating message.";
    foreach($errors as $error) {
      $reply .= " $error ";
    }
}

$majorContext = "Databases";

include_once('specify_library.php');

echo pageheader("specimen","off");

?>
<div id="sidenav">
  <ul>
    <li><a href="addenda.html">SEARCH HINTS</a></li>
    <li><a href="addenda.html#policy">DISTRIBUTION AND USE POLICY</a></li>
  <hr />
    <li><a href="botanist_index.html">BOTANISTS</a></li>
    <li><a href="publication_index.html">PUBLICATIONS</a></li>
    <li><a href="specimen_index.html">SPECIMENS</a></li>
    <li><a href="image_search.php" >IMAGES</a></li>
  <hr />
    <li><a href='http://flora.huh.harvard.edu/HuCards/'>Hu Card Index</a></li>
    <li><a href='http://econ.huh.harvard.edu/'>ECON Artifacts & Products</a></li>
    <li><a href="add_correct.html" class="active">Contribute additions/corrections</a></li>
    <li><a href="comment.html">Send comments/questions</a></li>

  </ul>
</div>  <!-- sidenav ends -->


<div id="main">
                <!-- main content begins -->
                <div id="main_text">

<h3>Botanical Indexes</h3>

<h2>Comment submission</h2>

<?php

echo $reply;

?>


                    </div>
        </div>

        <!-- main content ends -->

<?php

echo pagefooter('specimen');

?>

<?php
namespace App\Core;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer
{
    private $mailer;
    private $config;
    private $hooks;
    
    public function __construct()
    {
        $this->config 	= Config::getInstance();
        $this->hooks 	= Framework::getHooks();
        $this->initialize();
    }
    
    private function initialize()
    {
        
		$this->mailer = new PHPMailer(true);
		$this->mailer->SMTPDebug = $this->config->get( 'mail.smtp_debug' );
		$mail->Debugoutput 	= 'html';
        
        // Server settings
		if( $this->config->get( 'mail.smtp_auth' ) == true ){
			$this->mailer->isSMTP();
		}else{
			$this->mailer->isMail();
		}        
        
        $this->mailer->Host 		= $this->config->get( 'mail.host' );
        $this->mailer->SMTPAuth 	= $this->config->get( 'mail.smtp_auth' );
        $this->mailer->Username 	= $this->config->get( 'mail.username' );
        $this->mailer->Password 	= $this->config->get( 'mail.password' );
        $this->mailer->SMTPSecure 	= $this->config->get( 'mail.encryption' ) == "ssl" ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;        
        $this->mailer->Port 		= $this->config->get( 'mail.encryption' ) == "ssl" ? $this->config->get( 'mail.ssl_port' ) : $this->config->get( 'mail.tls_port' );
		
        // Default from
        $this->mailer->setFrom($this->config->get( 'mail.address.from' ), $this->config->get( 'mail.address.name' ) );
        
        // Default settings
        $this->mailer->isHTML( $this->config->get( 'mail.is_html' ) );
        $this->mailer->CharSet = $this->config->get( 'mail.charset');
		
    }
        
    private function setFrom( $email, $name )
    {
        
		$this->mailer->setFrom( $email, $name );
		
    }
    
    public function send( $to, $subject, $body, $attachments = [] )
    {
        try {
			
            // 	Execute before send hook
            //	$mailData = $this->hooks->exec( 'beforeSendMail', [
            //	    'to' 			=> $to,
            //	    'subject' 		=> $subject,
            //	    'body' 			=> $body,
            //	    'attachments' 	=> $attachments
            //	]);
            
            //	if ($mailData === false) {
            //	    return false; // Hook prevented sending
            //	}
            //	
            //	// Extract modified data
            //	extract($mailData);
            
            // Clear previous recipients and attachments
            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();
            
            // Set recipients
            if (is_array($to)) {
                foreach ($to as $email => $name) {
                    if (is_numeric($email)) {
                        $this->mailer->addAddress($name);
                    } else {
                        $this->mailer->addAddress($email, $name);
                    }
                }
            } else {
                $this->mailer->addAddress($to);
            }
            
            // Set subject and body
            $this->mailer->Subject 	= $subject;
            $this->mailer->Body 	= $body;
            $this->mailer->AltBody 	= strip_tags( $body );
            
            // Add attachments
            foreach ($attachments as $attachment) {
                if (is_array($attachment)) {
                    $this->mailer->addAttachment($attachment['path'], $attachment['name'] ?? '');
                } else {
                    $this->mailer->addAttachment($attachment);
                }
            }
            
            // 	Execute before final send hook
            //	$this->hooks->exec('onMailSend', [
            //	    'mailer' 	=> $this->mailer,
            //	    'subject' 	=> $subject
            //	]);
            
            $result = $this->mailer->send();
			
			if( !$result ){
				echo $this->mailer->ErrorInfo;
			}
            
            // 	Execute after send hook
            //	$this->hooks->exec( 'afterSendMail', [
            //	    'to' 		=> $to,
            //	    'subject' 	=> $subject,
            //	    'success' 	=> $result
            //	]);
            
            return $result;
            
        } catch (Exception $e) {
            // Execute error hook
            //	$this->hooks->exec('onMailError', [
            //	    'error' 	=> $e->getMessage(),
            //	    'to' 		=> $to,
            //	    'subject' 	=> $subject
            //	]);
            
            #	throw new Exception("Mailer Error: " . $e->getMessage());
			
        }
    }
    
    public function setFrom($email, $name = '')
    {
        $this->mailer->setFrom($email, $name);
        return $this;
    }
    
    public function addReplyTo($email, $name = '')
    {
        $this->mailer->addReplyTo($email, $name);
        return $this;
    }
    
    public function addCC($email, $name = '')
    {
        $this->mailer->addCC($email, $name);
        return $this;
    }
    
    public function addBCC($email, $name = '')
    {
        $this->mailer->addBCC($email, $name);
        return $this;
    }
    
    public function template($template, $data = [])
    {
        
		$smarty = Framework::getSmarty();        
        // Assign data to template
        foreach ($data as $key => $value) {
            $smarty->assign($key, $value);
        }
        
        $templatePath = Config::getInstance()->get( "smarty.template_dir", "" ) == "" ? APP_PATH . "/views/emails/{$template}.tpl" : Config::getInstance()->get( "smarty.template_dir" );
        
        if (!file_exists($templatePath)) {
            throw new Exception( "Email template not found: {$templatePath}" );
        }
        
        return $smarty->fetch($templatePath);
		
    }
    
    // Proxy method calls to PHPMailer
    public function __call($method, $args)
    {
        if (method_exists($this->mailer, $method)) {
            return call_user_func_array([$this->mailer, $method], $args);
        }
        
        throw new Exception("Method {$method} not found in Mailer");
    }
}


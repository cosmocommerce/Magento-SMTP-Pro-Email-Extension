<?php

/**
 * An SMTP self test for the SMTPPro Magento extension
 *
 *
 * @author Ashley Schroder (aschroder.com)
 * @copyright  Copyright (c) 2010 Ashley Schroder
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */


class Aschroder_SMTPPro_IndexController
	extends Mage_Adminhtml_Controller_Action {
		
	public static $CONTACTFORM_SENT;
	private $TEST_EMAIL = "hello.default@example.com";

	public function indexAction() {

		Mage::log("Running SMTP Pro Self Test");
		
		#report development mode for debugging
		$dev = Mage::helper('smtppro')->getDevelopmentMode();
		Mage::log("Development mode: " . $dev);
		
		$success = true;
		$websiteModel = Mage::app()->getWebsite($this->getRequest()->getParam('website'));
		$this->TEST_EMAIL = Mage::getStoreConfig('trans_email/ident_general/email', $websiteModel->getId());

		$msg = "邮件自检结果";
		
		$msg = $msg . "<br/>服务器连通性:";
		Mage::log("Raw connection test....");
		
		
		$googleapps = Mage::helper('smtppro')->getGoogleApps();
		$smtpEnabled = Mage::helper('smtppro')->getSMTP();
		$sesEnabled = Mage::helper('smtppro')->getSES();
		
		if($googleapps) {
			$msg = $msg . "<br/>Google Apps/Gmail 配置项";
			$host = "smtp.gmail.com";
			$port = 587;
		} else if ($smtpEnabled) {
			$msg = $msg . "<br/>SMTP信息";
			$host = Mage::getStoreConfig('system/smtpsettings/host', $websiteModel->getId());
			$port = Mage::getStoreConfig('system/smtpsettings/port', $websiteModel->getId());
		} else if ($sesEnabled) {
			// no connectivity test - either disabled or SES...
			$msg = $msg . "<br/>Amazon SES 服务未测试";
			Mage::log("skipped, SES.");
		} else {
			$msg = $msg . "<br/> 插件已被禁用";
			Mage::log("skipped, disabled.");
		}
		

		if ($googleapps || $smtpEnabled) {
			$fp = false;
			
			try {
				$fp = fsockopen($host, $port, $errno, $errstr, 15);
			} catch ( Exception $e) {
				// An error will be reported below.
			}
	
			Mage::log("Complete");
	
			if (!$fp) {
				$success = false;
				$msg = $msg . "<br/> 连接错误,原因: " . $errstr . "(" . $errno . ")";
			 	$msg = $msg . "<br/> 端口要求: " . $port;
			} else {
				$msg = $msg . "<br/> 连接成功";
				fclose($fp);
			}
		}

		$to = Mage::getStoreConfig('contacts/email/recipient_email', $websiteModel->getId());

		$mail = new Zend_Mail();
		$sub = "CosmoShop邮件测试标题";
		$body = 
			"您好,\n\n" .
			"如果您看到本邮件,说明系统配置成功  \n\n" .
			"祝贺,\n CosmoShop";

	        $mail->addTo($to)
	        	->setFrom($this->TEST_EMAIL)
        		->setSubject($sub)
	            ->setBodyText($body);

		if ($dev != "supress") {
			
			Mage::log("Actual email sending test....");
			$msg = $msg . "<br/> 发送测试邮件给联系人 " . $to . ":";
			
	        try {
				$transport = Mage::helper('smtppro')->getTransport($websiteModel->getId());
				
			 	
				$mail->send($transport);
				
				Mage::dispatchEvent('smtppro_email_after_send',
				array('to' => $to,
			 			'template' => "自检测试",
						'subject' => $sub,
						'html' => false,
			 			'email_body' => $body));
				
				$msg = $msg . "<br/> 测试邮件发送成功.";
				Mage::log("Test email was sent successfully");
				
				
	    	} catch (Exception $e) {
				$success = false;
				$msg = $msg . "<br/> 无法发送邮件,原因: " . $e->getMessage() . "...";
			 	$msg = $msg . "<br/> 请检查用户名和密码.";
				Mage::log("Test email was not sent successfully: " . $e->getMessage());
	    	}
		} else {
			Mage::log("Not sending test email - all mails currently supressed");
			$msg = $msg . "<br/> 没有发送任何邮件,因为目前是模拟模式.";
		}
		
		// Now we test that the actual core overrides are occuring as expected.
		// We trigger the contact form email, as though a user had done so.

		Mage::log("Actual contact form submit test...");
		
		self::$CONTACTFORM_SENT = false;
		$this->_sendTestContactFormEmail();
		
		// If everything worked as expected, the observer will have set this value to true.
		if (self::$CONTACTFORM_SENT) {
			$msg = $msg . "<br/> 联系邮件已发送.";
		} else {
			$success = false;
			$msg = $msg . "<br/> 联系邮件未发送";
		}
		
		Mage::log("Complete");

		if($success) {
			$msg = $msg . "<br/> 自检完成,如果有疑问请联系我们.";
			Mage::getSingleton('adminhtml/session')->addSuccess($msg);
		} else {
			$msg = $msg . "<br/> 自检完成,如果问题不知道如何解决,请联系我们帮助支持.";
			Mage::getSingleton('adminhtml/session')->addError($msg);
		}
 
		$this->_redirectReferer();
	}

	private function _sendTestContactFormEmail() {
		
		$postObject = new Varien_Object();
		$postObject->setName("邮件自检");
		$postObject->setComment("邮件自检成功");
		$postObject->setEmail($this->TEST_EMAIL);
		
		$mailTemplate = Mage::getModel('core/email_template');
		/* @var $mailTemplate Mage_Core_Model_Email_Template */
		
		include Mage::getBaseDir() . '/app/code/core/Mage/Contacts/controllers/IndexController.php';
		
		$mailTemplate->setDesignConfig(array('area' => 'frontend'))
			->setReplyTo($postObject->getEmail())
			->sendTransactional(
				Mage::getStoreConfig(Mage_Contacts_IndexController::XML_PATH_EMAIL_TEMPLATE),
				Mage::getStoreConfig(Mage_Contacts_IndexController::XML_PATH_EMAIL_SENDER),
				Mage::getStoreConfig(Mage_Contacts_IndexController::XML_PATH_EMAIL_RECIPIENT),
				null,
				array('data' => $postObject)
			);

	}

} 

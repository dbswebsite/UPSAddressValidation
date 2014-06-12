<?php
/**
* @file UPSSoapXAVClient.php
*
* Handles the UPS Address Validation using the UPS API and SDK. This
* code is invoked using POST data from a form via an ajax request.
* At this time UPS only supports US and Puerto Rico (but some docs also say
* Canada). We default to using the street level UPS API.
*
* On successful Ajax requests, json encoded data with UPS address data is
* returned. On anything UPS considers an invalid address, a simple 'invalid' is
* returned as string.
*
* @author @dbsinteractive 2014-05-19, adapted from UPS example code.
*/


/*
// TMP dummy POST just for testing.
$_POST = array(
	"company" 	=> "",
	"address1" 	=> "517 S. Fourth Blvd",
	"address2" 	=> "",
	"city"		=> "Louisville",
	"state"		=> "Co",
	"zip"		=> "55502",
	"Country"		=> "US"
);
*/

error_reporting( E_ALL );
define( 'DEBUG', true );

// RUN it 
UPSValidateAdress::makeSoapRequest();
/////////////////////////////////////

// the_class()
class UPSValidateAdress {
	/* Configuration constants */
	// UPS credentials
	const ACCESS = "ups access credential";
	const USERID = "ups user";
	const PASSWD = "ups password";

	// Required wsdl file, originally in the SDK as:
	// Address_Validation_Street_Level/Street Level Address Validation for SHIPPING/XAVWebServices/SCHEMAS-WSDLs/XAV.wsdl
	const WSDL = "path/to/XAV.wsdl";
	// for UPS API
	const OPERATION = "ProcessXAV";
	// testing URL, only works with CA and NY ...
	//	const ENDPOINTURL = 'https://wwwcie.ups.com/webservices/XAV';
	// live endpoint ...	
	const ENDPOINTURL = 'https://onlinetools.ups.com/webservices/XAV';
	// for debugging only ...
	const DEBUGFILENAME = "/tmp/UPSResult.xml";
	// Street level, or just City/state/zip :: not really tested
	const STREETLEVEL = true;
	const AJAX = true;

	/**
	* @return array $soap_request of data to be sent with SOAP call.
	*/
	private function processXAV() {
		$soap_request = array();

		// NOTE: "1" seems to be the only viable option
		$soap_request['Request'] = array( 'RequestOption' => 1 );

		if ( ! self::STREETLEVEL ) {
			// if this is set, there will be no street level validation
     	     $soap_request['RegionalRequestIndicator'] = '';
		}

		// format the POST data to the required UPS format
		$soap_request['AddressKeyFormat'] = self::makeFromPost();
		
		return $soap_request;
	}

	/**
	* @return SOAP response as XML object data, or JSON if ajaxed.
	*/
	static function makeSoapRequest() {
		$usernameToken = $serviceAccessLicense = $upss = array();

		try {
			$mode = array(
				'soap_version' => 'SOAP_1_1',  // use soap 1.1 client
				'trace' => 1
			);

			// initialize SOAP client
			$client = new SoapClient( self::WSDL, $mode);

			//set endpoint url, note there is a testing and live url.
			$client->__setLocation( self::ENDPOINTURL );

			//create SOAP header
			$usernameToken['Username'] = self::USERID;
			$usernameToken['Password'] = self::PASSWD;
			$serviceAccessLicense['AccessLicenseNumber'] = self::ACCESS;
			$upss['UsernameToken'] = $usernameToken;
			$upss['ServiceAccessToken'] = $serviceAccessLicense;

			$soap_header = new SoapHeader( 'http://www.ups.com/XMLSchema/XOLTWS/UPSS/v1.0','UPSSecurity', $upss );
			$client->__setSoapHeaders( $soap_header );

			//get SOAP response, fingers crossed
			$soap_response = $client->__soapCall( self::OPERATION ,array( self::processXAV() ) );
			
			// make sure the response is OK.
			if ( 'Success' !== $soap_response->Response->ResponseStatus->Description ) {
				error_log( 'Error on Soap Response from UPS' );
				die( 'Error on SOAP response from UPS' );
			}

			// extract just the address stuff we need from the SOAP response XML
			$xml_object = new SimpleXMLElement( $client->__getLastResponse() );
			$xml_response_data = $xml_object->children( 'http://schemas.xmlsoap.org/soap/envelope/' )->Body->children('http://www.ups.com/XMLSchema/XOLTWS/xav/v1.0')->XAVResponse;

			// If our address is validated, we should be good to go. If not, there
			// may be one or more possible Candidates.
			$is_valid_address =  array_key_exists( 'ValidAddressIndicator', $xml_response_data );

			//save SOAP request and response to file for debugging purposes
			if ( DEBUG ) {
				$fw = fopen(self::DEBUGFILENAME , 'w');
				fwrite($fw , $client->__getLastResponse() . "\n");
				fclose($fw);
			}

			// requires libxml2 for nice formatting of xml response output
//			if ( DEBUG ) echo shell_exec("xmllint --format  " . self::DEBUGFILENAME );

			if ( self::AJAX ) {
				if ( $is_valid_address ) {
					// The supplied address is OK, we will accept whatever the user gave us on the form submission,
					// and return the reformatted, normalized address to hand back to the form.
					die( json_encode( $xml_response_data ) ); 
				}

				// Else return 'invalid' as error code and presumably force user to try again.
				// TODO: optionally return any available candidates.
				die( 'invalid' );
			}

			// UPS address data as object
			return $xml_response_data;
			
		} catch( Exception $e ) {
			if ( DEBUG ) print_r ($e);
			error_log( 'UPS SOAP Request failed: ' . $e->getMessage() );
		}
	}

	/**
	* @return array of address data, properly formatted for UPS API
	*
	* Takes POST data and translates that to the appriopate format.
	*
	* TODO: abstract form fields, they are hardcoded here.
	*/
	private function makeFromPost() {
		$address_key_format = array();

		if ( ! $_POST ) die( "Error, no data" );
		$post = array_map( 'strip_tags', array_map( 'trim', $_POST ) );

		// TODO: POST values are hardwired 
 		$country = $post['Country'];

		// handle postal code for US vs Canada
		if ( 'US' === $country ) {
			// split US zipcode
			preg_match( "/(\d{3,5})-?(\d{4})?/", $post['zip'], $matches );
			$zip = $matches[ 1 ];
			$zip_extended = ( isset( $matches[2] ) ) ? $matches[ 2 ] : '';
		} else {
			// Canada ... assuming they don't have the same pattern as US zipcodes.
			$zip = $post['zip'];
			$zip_extended = null;
		}

		$address_key_format['ConsigneeName'] = $post['company'];
		$address_key_format['AddressLine'] = array (
			$post['address1'],
			$post['address2']
		 );
 		$address_key_format['PoliticalDivision2'] = $post['city'];
 		$address_key_format['PoliticalDivision1'] = $post['state'];
 		$address_key_format['PostcodePrimaryLow'] = $zip;
 		$address_key_format['PostcodeExtendedLow'] = $zip_extended;
		$request['RegionalRequestIndicator'] = '';

//  		$address_key_format['Urbanization'] = 'porto arundal'; /* FIXME: only used in Puerto Rico, country code == PR */
 		$address_key_format['CountryCode'] = $country;

		return $address_key_format;
	}
}

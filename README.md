UPSAddressValidation
====================

PHP script to invoke the UPS Address Validation API and return ajaxed results.

You must have a proper UPS developer account and credentials and a copy of the SDK. The XAV.wsdl file from the SDK must be included as well.

This code is invoked using POST data from a form via an ajax request. At this time UPS only supports US and Puerto Rico (but some docs also say Canada, so maybe that too). We default here to using the street level UPS API.

A validated address has a ValidAddressIndicator XML element. In some cases, UPS might even find minor errors, fix them, and return one result candidate as "Validated". Whereas an invalid/ambiguous address has a AmbiguousAddressIndicator element or a NoCandidatesIndicator element. The first 2 both have "Candidates". That seems to be the only distinction. AmbiguousAddress can have at least 5 candidates.


On successful Ajax requests, json encoded data with the UPS address data is returned. On anything UPS considers an invalid or ambiguous address, a simple 'invalid' is returned as string.

Feature: webdav

  Scenario: Support current-user-principal
    Given sending "PROPFIND" to "/" with xml body:
    """
  <?xml version="1.0" encoding="UTF-8"?>
  <A:propfind xmlns:A="DAV:">
  <A:prop>
  <A:current-user-principal/>
  <A:resourcetype/>
  </A:prop>
  </A:propfind>"
    """
    Then the HTTP status code should be "207"
    And the response should be:
  """
  "<?xml version="1.0" encoding="utf-8"?>
  <d:multistatus xmlns:d="DAV:" xmlns:s="http://sabredav.org/ns">
  <d:response>
  <d:href>/remote.php/dav/</d:href>
  <d:propstat>
  <d:prop>
  <d:resourcetype>
  <d:collection/>
  </d:resourcetype>
  </d:prop>
  <d:status>HTTP/1.1 200 OK</d:status>
  </d:propstat>
  <d:propstat>
  <d:prop>
  <d:current-user-principal/>
  </d:prop>
  <d:status>HTTP/1.1 404 Not Found</d:status>
  </d:propstat>
  </d:response>
  </d:multistatus>"
  """

<?xml version="1.0" encoding="UTF-8"?>

<xsl:stylesheet version="1.0"
xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#"
xmlns:foaf="http://xmlns.com/foaf/0.1/"
xmlns:skos="http://www.w3.org/2004/02/skos/core#"
xmlns:bio="http://purl.org/vocab/bio/0.1/"
>

<xsl:template match="/rdf:RDF">
  <html>
  <head>
  <title>HUH Botanist Record</title>
  </head>
  <body>
  <h2>HUH Botanist Record</h2>
  <table border="1">
    <tr bgcolor="#b8c0d2">
      <th>Type</th>
      <th>Botanist's Name</th>
      <th>Note</th>
      <th>Variant names</th>
      <th>Birth</th>
      <th>Death</th>
      <th>Interest</th>
      <th>Links</th>
      <th>GUID</th>
    </tr>
    <xsl:for-each select="foaf:Person|foaf:Group|foaf:Agent">
    <xsl:variable name="personid" select="attribute::rdf:about"/>
    <tr>
      <td>
         <xsl:if test="self::foaf:Person">Person</xsl:if>
         <xsl:if test="self::foaf:Group">Team</xsl:if>
         <xsl:if test="self::foaf:Agent">Agent</xsl:if>
      </td>
      <td><xsl:value-of select="rdfs:label"/></td>
      <td><xsl:value-of select="skos:note"/></td>
      <td>
         <xsl:value-of select="foaf:name[2]"/><xsl:if test="foaf:name[3] != ''"><br/></xsl:if>
         <xsl:value-of select="foaf:name[3]"/><xsl:if test="foaf:name[4] != ''"><br/></xsl:if>
         <xsl:value-of select="foaf:name[4]"/><xsl:if test="foaf:name[5] != ''"><br/></xsl:if>
         <xsl:value-of select="foaf:name[5]"/><xsl:if test="foaf:name[6] != ''"><br/></xsl:if>
         <xsl:value-of select="foaf:name[6]"/>
      </td>
      <td><xsl:value-of select="../bio:Birth/bio:date"/></td>
      <td><xsl:value-of select="../bio:Death/bio:date"/></td>
      <td><xsl:value-of select="foaf:topic_interest"/></td>
      <td>
         <a>
           <xsl:attribute name="href">
              <xsl:value-of select="foaf:isPrimaryTopicOf[1]/attribute::rdf:resource"/>
            </xsl:attribute>
            <xsl:value-of select="foaf:isPrimaryTopicOf[1]/attribute::rdf:resource"/>
         </a>
         <xsl:if test="foaf:isPrimaryTopicOf[2]/attribute::rdf:resource != ''">
         <br/>
         <a>
           <xsl:attribute name="href">
              <xsl:value-of select="foaf:isPrimaryTopicOf[2]/attribute::rdf:resource"/>
            </xsl:attribute>
            <xsl:value-of select="foaf:isPrimaryTopicOf[2]/attribute::rdf:resource"/>
         </a>
         </xsl:if>
      </td>
      <td><xsl:value-of select="$personid"/></td>
    </tr>
    </xsl:for-each>
  </table>
  </body>
  </html>
</xsl:template>

</xsl:stylesheet> 

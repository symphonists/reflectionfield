# Field: Reflection

This field generates values based on other fields from the same entry.
Uses XPath and optionally XSLT.

## Usage

When saving an entry Reflection field creates an internal `data` structure similar to what Symphony provides on the front-end. Besides the field data contextual parameters like the root and workspace paths and the section handle are available as well.

```xml
<data>
	<params>
		<today>2016-08-02</today>
		<current-time>12:34</current-time>
		<this-year>2016</this-year>
		<this-month>08</this-month>
		<this-day>02</this-day>
		<timezone>+02:00</timezone>
		<website-name>Example Website</website-name>
		<root>https://example.com</root>
		<workspace>https://example.com/workspace</workspace>
		<http-host>example.com</http-host>
		<upload-limit>5242880</upload-limit>
		<symphony-version>2.7.0</symphony-version>
	</params>
	<reflection-field-handle>
		<section id="…" handle="…">…</section>
		<entry id="…">
			<field-one></field-one>
            <field-two>
                <item handle=""></item>
                <item handle=""></item>
            </field-two>
			<system-date>
				<created iso="" timestamp="" time="" weekday="" offset="">…</created>
				<modified iso="" timestamp="" time="" weekday="" offset="">…</modified>
			</system-date>
		</entry>
	</reflection-field-handle>
</data>
```

**Note:** Version 2.0 changed the `data` structure to conform with the front-end. The `root` and `workspace` nodes moved to the parameter pool. The `entry-id` node was removed as the id was already available on the `entry` node.

### XSLT Utilities

XSL templates are used to manipulate the XML data before building the reflection expression. Any template in `/workspace/utilities` can be attached to the field: it will be provided with the above XML. The extension expects you to return an XML structure again, that is then used inside the expression field.

### Expressions

Expressions are used to build the field's content. You can add static content or markup, dynamic values can be added using curly braces containing and xPath expression to find the needed data. If you don't use an XSLT utility, the xPath expression is evaluated against the above XML. If you transformed the source data using a template, the xPath is evaluated against the returned XML structure you created.

## Usage with XML Importer

Reflection Field assumes that the entry has already saved some data for the said field, and then seeks to update the database directly with the correct reflection generated content.
In the case of XML Importer this means that you have to include the Reflection field within the import items, otherwise XML Importer will not find any data within your database to update.
You can insert an empty string, this would be sufficient for Reflection Field to be able to save the necessary data.

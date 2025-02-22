# Datatypes

This document describes the concept of property data types as used by Wikibase.

## Overview

Property data types in Wikibase are rather insubstantial  * they are modeled by DataType objects, but such objects do not define any functionality of themselves. They merely act as a type safe ID for the data type.

Property data types are used to declare which kinds of values can be associated with a Property in a Snak. For each data type, the following things are defined:

* The type of DataValue to use for values (the “value type”).
* A localized name and description of the data type.
* ValueValidators that impose constraints on the allowed values.
* A ValueParser for parsing user input for a given type of property.
* Formatters for rendering snaks and values of the given type to various target formats.
* RDF mappings for representing snaks and values of the given type in RDF.

## Data Type Definitions

Property data types are defined in the global <code>$wgWBRepoDataTypes</code> and <code>$wgWBClientDataTypes</code> arrays, respectively.
These arrays are constructed at bootstrap time in [Wikibase.php] resp. [WikibaseClient.php] based on the information returned when including the files [WikibaseLib.datatypes.php], [Wikibase.datatypes.php], and [WikibaseClient.datatypes.php], respectively.

The <code>$wgWBRepoDataTypes</code> and <code>$wgWBClientDataTypes</code> are associative arrays that map property data types and value types to a set of constructor callbacks (aka factory methods).

Property data types and value types are used as keys in the <code>$wgWBRepoDataTypes</code> and <code>$wgWBClientDataTypes</code>. They are distinguished by the prefixes “VT:” and “PT:”. For instance, the string value type would use the key “VT:string”, while the url data type would use the key “PT:url”.

Logically, the value type defines the structure of the value, while the property data type defines the interpretation of the value. Property data types may impose additional constraints on the values, or impact how they are rendered or exported.

Each key is associated with a map that provides the following fields:

* value-type (repo and client)
  * The value type to use for this data type (not used for value type keys).
* rdf-uri (repo only)
  * The URI to use in RDF to identify the data type. It is good practice to use a URI that will resolve to an RDF document that contains an OWL description of the data type, defining it to be of rdf:type wikibase:PropertyType, and providing a rdfs:label and a rdfs:comment describing the type. If no URI is defined explicitly, a URI will be composed using the base URI of the Wikibase ontology, and adding a CamelCase version of the datatype ID (so that “foo-bar” would become “FooBar”).
* validator-factory-callback (repo only)
  * A callable that acts as a factory for the list of validators that should be used to check any user supplied values of the given data type. The callable will be called without any arguments, and must return a list of ValueValidator objects.
* parser-factory-callback (repo only)
  * A callable that acts as a factory for a ValueParser for this data type.
* formatter-factory-callback (repo and client)
  * A callable that acts as a factory for ValueFormatters for use with this data type.
* snak-formatter-factory-callback (repo and client)
  * A callable that acts as a factory for SnakFormatters for use with this data type. If not defined, a SnakFormatter is created from the ValueFormatter for the given data type.
* rdf-builder-factory-callback (repo only)
  * A callable that acts as a factory for [ValueSnakRdfBuilder] for use with this data type.
* rdf-data-type (repo only)
  * RDF/OWL data type of a property having this data type (either ObjectProperty or DatatypeProperty, use constants from [PropertyRdfBuilder]). Default is DatatypeProperty.

Since for each property data type the associated value type is known, this provides a convenient fallback mechanism  * If a desired callback field isn't defined for a given property data type, we can fall back to using the callback that is defined for the value type. For example, if there is no formatter-factory-callback field associated with the PT:url key, we may use the one defined for VT:string, since the url property data type is based on the string value type.

Extensions that wish to register a data type should use the [WikibaseRepoDataTypes] resp. [WikibaseClientDataTypes] hooks to provide additional data type definitions.

## Programmatic Access

Information about data types can be accessed programmatically using the appropriate service objects.

The data type definitions themselves are wrapped by a [DataTypeDefinitions] object* the DataType objects can be obtained from the [DataTypeFactory] service available via [WikibaseRepo::getDataTypeFactory()] and [WikibaseClient::getDataTypeFactory()]

[WikibaseRepo] also has [WikibaseRepo::getDataTypeValidatorFactory()] which returns a [DataTypeValidatorFactory] for obtaining the validators for each data type.

## Caveats

* **FIXME**  * the Methods <code>getSnakFormatterFactory()</code> does not yet use <code>$wgWikibaseDataTypeDefinitions</code>.

[DataTypeDefinitions]: @ref Wikibase::Lib::DataTypeDefinitions
[DataTypeFactory]: @ref Wikibase::Lib::DataTypeFactory
[WikibaseRepo]: @ref Wikibase::Repo::WikibaseRepo
[ValueSnakRdfBuilder]: @ref Wikibase::Rdf::ValueSnakRdfBuilder
[PropertyRdfBuilder]: @ref Wikibase::Rdf::PropertyRdfBuilder
[WikibaseClient::getDataTypeFactory()]: @ref Wikibase::Client::WikibaseClient::getDataTypeFactory()
[WikibaseRepo::getDataTypeFactory()]: @ref Wikibase::Repo::WikibaseRepo::getDataTypeFactory()
[WikibaseRepo::getDataTypeValidatorFactory()]: @ref Wikibase::Repo::WikibaseRepo::getDataTypeValidatorFactory()
[DataTypeValidatorFactory]: @ref Wikibase::Repo::DataTypeValidatorFactory
[WikibaseLib.datatypes.php]: @ref WikibaseLib.datatypes.php
[Wikibase.datatypes.php]: @ref Wikibase.datatypes.php
[WikibaseClient.datatypes.php]: @ref WikibaseClient.datatypes.php
[WikibaseClient.php]: @ref client/WikibaseClient.php
[Wikibase.php]: @ref repo/Wikibase.php
[WikibaseRepoDataTypes]: @ref WikibaseRepoDataTypes
[WikibaseClientDataTypes]: @ref WikibaseClientDataTypes

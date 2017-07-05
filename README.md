# PackageFactory.FusionDslExplorer

> CLI Commands to simulate dsl-transpilation or to remove a fusion dsl from your codebase

## Usage 

- `/flow dsl:simulate __dsl_identifier__ [--package-key __package_key__] [--fusion-file __filename__] `
- `/flow dsl:eject __dsl_identifier__ [--package-key __package_key__] [--fusion-file __filename__] `

The dsl-identifier is mandatory and either package-key or fusion-file have to be specified.

## Installation

PackageFactory.AtomicFusion.AFX is available via packagist. Just add `"packagefactory/fusiondslexplorer" : "~1.0.0"`
to the require-section of the composer.json or run `composer require packagefactory/fusiondslexplorer`.

__We use semantic-versioning so every breaking change will increase the major-version number.__

## License

see [LICENSE file](LICENSE)

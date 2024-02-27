# YAML

Castor provides a `yaml_parse()` and `yaml_dump()` functions that will parse or 
dump YAML content and returns an array using the [symfony/yaml](https://symfony.com/doc/current/components/yaml.html) component:

```php
use Castor\Attribute\AsTask;

use function Castor\{yaml_parse, yaml_dump};

#[AsTask()]
function yaml_parse_and_dump(): void
{
    $content = yaml_parse(file_get_contents('file.yaml'));
    
    file_put_contents('file.yaml', yaml_dump($content));
}
```

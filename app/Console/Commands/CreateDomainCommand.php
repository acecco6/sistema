<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

#[Signature('make:domain {name : El nombre del dominio (ej. Clubs)}')]
#[Description('Crea la estructura de carpetas y la entidad para un nuevo Dominio')]
class CreateDomainCommand extends Command
{

    public function handle()
    {
        // Obtener el argumento y formatearlo (Ej: clubs -> Clubs)
        $domainName = ucfirst($this->argument('name'));

        // Definir la ruta base dentro de app/Domain/
        $domainPath = app_path("Domain/{$domainName}");

        // Definir la ruta base dentro de app/Domain/
        $applicationPath = app_path("Application/{$domainName}");

        if (File::exists($domainPath) || File::exists($applicationPath)) {
            $this->error("El dominio '{$domainName}' ya existe.");
            return Command::FAILURE;
        }

        // Subcarpetas a crear
        $subfolders = [
            'Entities',
            'ValueObjects',
            'Events',
            'Exceptions',
            'Repositories',
            'Services'
        ];

        // Crear la estructura de directorios
        foreach ($subfolders as $folder) {
            File::makeDirectory("{$domainPath}/{$folder}", 0755, true, true);
        }

        // Generar el archivo de la Entidad base
        $this->createEntity($domainName, $domainPath);

        $this->info("¡Estructura del dominio '{$domainName}' creada con éxito!");
        return Command::SUCCESS;
    }

    protected function createEntity($domainName, $domainPath)
    {
        // Quitar la 's' final para el nombre del archivo si es plural (opcional, ej: Clubs -> Club)
        $entityName = rtrim($domainName, 's');

        $entityPath = "{$domainPath}/Entities/{$entityName}.php";

        // Contenido básico de la clase PHP
        $content = "<?php\n\n" .
            "namespace App\\Domain\\{$domainName}\\Entities;\n\n" .
            "class {$entityName}\n" .
            "{\n" .
            "    // Código de tu entidad\n" .
            "}\n";

        File::put($entityPath, $content);
    }
}

<?php

use Dr2g\ErpLibs\Models\Model;
use Dr2g\ErpLibs\Models\Attributes\Table;
use Dr2g\ErpLibs\Models\Attributes\Column;
use Dr2g\ErpLibs\Models\Attributes\HasMany;
use Dr2g\ErpLibs\Models\Attributes\ManyToMany;

#[Table(name: 'users')]
class User extends Model
{
    #[Column(type: 'VARCHAR(255)', nullable: false)]
    public string $name;
    
    #[Column(type: 'VARCHAR(255)', nullable: false, unique: true)]
    public string $email;
    
    #[Column(type: 'VARCHAR(255)', nullable: false)]
    public string $password;
    
    #[Column(type: 'DECIMAL(10,2)', nullable: false, default: 0)]
    public float $balance = 0.00;
    
    #[Column(type: 'JSON', nullable: true)]
    public ?array $preferences = null;
    
    #[HasMany(related: 'Post')]
    public array $posts;
    
    #[ManyToMany(related: 'Role')]
    public array $roles;
}

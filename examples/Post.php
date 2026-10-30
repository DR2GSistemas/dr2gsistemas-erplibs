<?php

use Dr2g\ErpLibs\Models\Model;
use Dr2g\ErpLibs\Models\Attributes\Table;
use Dr2g\ErpLibs\Models\Attributes\Column;
use Dr2g\ErpLibs\Models\Attributes\BelongsTo;

#[Table(name: 'posts')]
class Post extends Model
{
    #[Column(type: 'BIGINT UNSIGNED', nullable: false)]
    public int $user_id;
    
    #[Column(type: 'VARCHAR(255)', nullable: false)]
    public string $title;
    
    #[Column(type: 'TEXT', nullable: false)]
    public string $content;
    
    #[Column(type: 'BOOLEAN', nullable: false, default: false)]
    public bool $published = false;
    
    #[BelongsTo(related: 'User')]
    public User $author;
}

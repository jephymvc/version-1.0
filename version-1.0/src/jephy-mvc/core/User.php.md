<?php
namespace App\Entities;

use App\Core\Entity;

class User extends Entity
{
    protected $table = 'users';
    
    // Define relationships
    protected function initialize()
    {
        $this->defineRelation('profile', 'hasOne', Profile::class, [
            'foreignKey' => 'user_id',
            'localKey' => 'id'
        ]);
        
        $this->defineRelation('posts', 'hasMany', Post::class, [
            'foreignKey' => 'user_id',
            'localKey' => 'id'
        ]);
        
        $this->defineRelation('roles', 'belongsToMany', Role::class, [
            'pivotTable' => 'user_roles',
            'foreignPivotKey' => 'user_id',
            'relatedPivotKey' => 'role_id'
        ]);
    }
    
    // Or use methods (alternative)
    public function profile()
    {
        return $this->hasOne(Profile::class);
    }
    
    public function posts()
    {
        return $this->hasMany(Post::class);
    }
    
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }
    
    public function activePosts()
    {
        return $this->hasMany( Post::class, 'user_id', 'id' )->where('status', 'active');
    }
}

// Usage examples:
$user = User::find(1)->with('profile', 'posts', 'roles');
echo $user->name;
echo $user->profile->bio;
echo count($user->posts);

// Using QueryBuilder with relationships
$users = User::where('active', 1)
->with('profile', ['posts' => function($query) {
	return $query->where('published', 1);
}])
->orderBy('created_at', 'DESC')
->paginate(20);

// Many-to-many operations
$user->roles()->attach($role);
$user->roles()->detach($role);
$user->roles()->sync([1, 2, 3]);

// Change tracking
$user = User::find(1);
$user->name = 'New Name';
echo $user->isDirty(); // true
echo $user->isDirty('name'); // true
echo $user->isDirty('email'); // false

$dirty = $user->getDirty(); // ['name' => 'New Name']

// Save only if dirty
if ($user->isDirty()) {
    $user->save();
}
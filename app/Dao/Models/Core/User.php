<?php

namespace App\Dao\Models\Core;

use App\Dao\Builder\DataBuilder;
use App\Dao\Entities\Core\DefaultEntity;
use App\Dao\Entities\Core\UserEntity;
use App\Dao\Repositories\Core\CrudRepository;
use App\Dao\Traits\ActiveTrait;
use App\Dao\Traits\DataTableTrait;
use App\Dao\Traits\OptionTrait;
use App\Facades\Model\RoleModel;
use App\Facades\Model\UserModel;
use App\Notifications\VerifyUserQueue;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as AuthMustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Kyslik\ColumnSortable\Sortable;
use Laravel\Sanctum\HasApiTokens;
use MBarlow\Megaphone\HasMegaphone;
use Mehradsadeghi\FilterQueryString\FilterQueryString as FilterQueryString;

class User extends Authenticatable implements AuthMustVerifyEmail
{
    use ActiveTrait;
    use CrudRepository;
    use DataTableTrait;
    use DefaultEntity;
    use FilterQueryString;
    use HasApiTokens;
    use HasFactory;
    use HasMegaphone;
    use MustVerifyEmail;
    use Notifiable;
    use OptionTrait;
    use Sortable;
    use UserEntity;

    protected $table = 'users';

    protected $primaryKey = 'id';

    protected $fillable = [
        'id',
        'name',
        'email',
        'phone',
        'password',
        'username',
        'role',
        'birthday',
        'level',
        'vendor',
        'active',
        'email_verified_at',
    ];

    public $sortable = [
        'name',
        'email',
        'roles.role_name',
    ];

    protected $filters = [
        'filter',
        'name',
        'system_role_name',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public $timestamps = true;

    public $incrementing = true;

    public static function field_name()
    {
        return 'name';
    }

    public function fieldSearching()
    {
        return $this->field_name();
    }

    protected static function newFactory()
    {
        return UserFactory::new();
    }

    public function fieldDatatable(): array
    {
        return [
            DataBuilder::build(UserModel::field_key())->name('ID')->show(false),
            DataBuilder::build(UserModel::field_name())->name('Name')->sort(),
            DataBuilder::build(UserModel::field_username())->name('Username')->sort(),
            DataBuilder::build(RoleModel::field_name())->name('Role'),
            DataBuilder::build(UserModel::field_email())->show(false)->name('Email'),
            DataBuilder::build(UserModel::field_phone())->name('Phone'),
            DataBuilder::build(UserModel::field_active())->name('Active')->show(false),
        ];
    }

    public function has_role()
    {
        return $this->hasOne(RoleModel::getModel(), RoleModel::field_key(), UserModel::field_role());
    }

    public function roleNameSortable($query, $direction)
    {
        $query = $this->queryFilter($query);
        $query = $query->orderBy(RoleModel::field_name(), $direction);

        return $query;
    }

    public function dataRepository()
    {
        $query = $this
            ->select($this->getSelectedField())
            ->leftJoinRelationship('has_role')
            ->active()
            ->sortable()
            ->filter();

        $query = env('PAGINATION_SIMPLE') ? $query->simplePaginate(env('PAGINATION_NUMBER')) : $query->paginate(env('PAGINATION_NUMBER'));

        return $query;
    }

    /**
     * Send the email verification notification.
     *
     * @return void
     */
    public function sendEmailVerificationNotification()
    {
        $this->notify(new VerifyUserQueue);
    }
}

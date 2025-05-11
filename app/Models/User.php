<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;
    use HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'password',
        'phone',
        'phone_verified_at',
        'telegram_chat_id',
        'role',
        'status'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }
    //relation to admins table
    public function admin()
    {
        return $this->hasOne(Admin::class);
    }
    //relation to borrowers table
    public function borrower()
    {
        return $this->hasOne(Borrower::class);
    }
    //relation to request_loan table
    public function requestLoan()
    {
        return $this->hasMany(RequestLoan::class);
    }
    //relation to credit_score table
    public function creditScore()
    {
        return $this->hasOne(CreditScore::class);
    }

    //relation to liveliness table has many
    public function liveliness()
    {
        return $this->hasMany(Liveliness::class);
    }

    //loans
    public function loans()
    {
        return $this->hasMany(Loan::class);
    }
}

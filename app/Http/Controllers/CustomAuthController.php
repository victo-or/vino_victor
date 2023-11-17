<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use App\Http\Controllers\CellierController;

class CustomAuthController extends Controller
{
    /**
     * Display a dashboard of the cellars and lists.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $totalsCelliers = CellierController::calculerTotalCellier();
        $totalsListes = ListeController::calculerTotalListe();

        $totalPrixCelliers = $totalsCelliers['totalPrixCelliers'];
        $totalQuantiteCelliers = $totalsCelliers['totalQuantiteCelliers'];
        $totalPrixListes = $totalsListes['totalPrixListes'];
        $totalQuantiteListes = $totalsListes['totalQuantiteListes'];
    
        return view('welcome', compact('totalPrixCelliers', 'totalQuantiteCelliers', 'totalPrixListes', 'totalQuantiteListes'));
    }

    /**
     * Show the form for creating a new user.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('auth.create');
    }

    /**
     * Store a newly created user in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'nom'      => 'required|min:2|max:20|regex:/^[^<>]*$/u',
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')
            ],
            'password' => 'required|min:6|confirmed|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{6,}$/'
        ],
        [
            'nom.required'      => "Veuillez saisir votre nom",
            'nom.min'           => "Votre nom doit contenir au moins 2 caractères",
            'nom.max'           => "Votre nom ne doit pas dépasser 20 caractères",
            'nom.alpha'         => "Votre nom ne doit contenir que des lettres",
            'email.required'    => "Veuillez saisir votre adresse email",
            'email.email'       => "Veuillez entrer un courriel valide",
            'email.unique'      => "Le courriel est associé à un compte existant", 
            'password.required' => "Veuillez saisir votre mot de passe",
            'password.min'      => "Votre mot de passe doit contenir au moins 6 caractères",
            'password.regex'    => "Votre mot de passe doit contenir au moins une minuscule, une majuscule, un chiffre et un caractère spécial",
            'password.confirmed'=> "Les mots de passe ne correspondent pas"
        ]);

        $user = new User;
        $user->nom = $request->input('nom');
        $user->email = $request->input('email');
        $user->password = Hash::make($request->input('password'));
        $user->save();
        return view('welcome', ['successMessage' => 'Compte créé avec succès, vous pouvez maintenant vous connecter.']);
    }

    /**
     * Authentification / log in of a user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function authentication(Request $request)
    {
        $request->validate([
            'email'    => 'required|email|exists:users',
            'password' => 'required'
        ], [
            'email.required'     => "Veuillez saisir votre adresse email",
            'email.email'        => "Veuillez entrer une adresse email valide",
            'email.exists' => "Ce courriel n'est pas associé à un compte",
            'password.required'  => "Veuillez saisir votre mot de passe",
        ]);
        try {

            $credentials = $request->only('email', 'password');

            if (!Auth::validate($credentials)) {
                return redirect('login')
                    ->withErrors([
                        'erreur' => "L'adresse courriel ou le mot de passe est incorrect"
                    ]);
            }

            $user = Auth::getProvider()->retrieveByCredentials($credentials);

            Auth::login($user, $request->get('remember'));

            return redirect()->route('welcome'); 
        } catch (\Exception $e) {
            return redirect('login')
                ->withErrors([
                    'erreur' => "Une erreur s'est produite lors de l'authentification"
                ]);
        }
    }

    /**
     * Log out a user in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function logout(){
        Auth::logout();
        Session::flush();
        return redirect(route('login'))->withSuccess('Vous êtes déconnecté');
    }

    /**
     * Display the user's informations.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\Response
     */
    public function show(User $user)
    {
        return view('utilisateur.index', ['user' => $user]);
    }

    /**
     * Show the form for editing the user's informations.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\Response
     */
    public function edit(User $user)
    {
        return view('utilisateur.edit', ['user' => $user]);
    }

    /**
     * Update the user's informations in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, User $user)
    {
        $request->validate([
            'nom'   => 'required|min:2|max:20|alpha',
            'email' => 'required|email',
        ],
        [
            'nom.required'   => 'Veuillez saisir votre nom',
            'nom.min'        => 'Votre nom doit contenir au moins 2 caractères',
            'nom.max'        => 'Votre nom ne doit pas dépasser 20 caractères',
            'nom.alpha'      => 'Votre nom ne doit contenir que des lettres',
            'email.required' => 'Veuillez saisir votre adresse courriel', 
            'email.email'    => 'Veuillez saisir un courriel valide'
        ]); 

        try {
            $user->update([
                'nom' => $request->nom,
                'email' => $request->email
            ]);
    
            return redirect(route('profil.show', $user->id))->withSuccess('Profil mis à jour avec succès');
        } catch (\Exception $e) {
            return redirect(route('profil.edit', $user->id))->withErrors(['erreur' => "Une erreur s'est produite lors de la mise à jour du profil"]);
        }
    }

    /**
     * Show the form for changing the password.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\Response
     */
    public function changePassword(User $user)
    {
        return view('utilisateur.edit-password', ['user' => $user]);
    }

    /**
     * Change and stock the new password.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\Response
     */
    public function stockNewPassword(Request $request, User $user)
    {
        $request->validate([
            'oldPassword' => 'required',
            'password'    => 'required|min:6|confirmed|different:oldPassword|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{6,}$/'
        ],
        [
            'oldPassword.required'  => "Veuillez saisir votre ancien mot de passe",
            'password.required'     => "Veuillez saisir votre nouveau mot de passe",
            'password.min'          => "Votre mot de passe doit contenir au moins 6 caractères",
            'password.regex'        => "Votre mot de passe doit contenir au moins une minuscule, une majuscule, un chiffre et un caractère spécial",
            'password.confirmed'    => "Les mots de passe ne correspondent pas",
            'password.different'    => "Le nouveau mot de passe doit être différent de l'ancien"
        ]); 

        if (Hash::check($request->oldPassword, $user->password)) {
            try {
                $user->password = Hash::make($request->input('password'));
                $user->save();
                return redirect(route('profil.show', $user->id))->withSuccess('Mot de passe mis à jour avec succès');
            } catch (\Exception $e) {
                return redirect(route('profil.edit', $user->id))->withErrors(['erreur' => "Une erreur s'est produite lors du changement du mot de passe"]);
            }
        } else {
            return redirect(route('profil.change-password', $user->id))->withErrors(['erreur' => "L'ancien mot de passe est incorrect"]);
        }
    }

    /**
     * Display a forgot-password form.
     *
     * @return \Illuminate\Http\Response
     */
    public function forgotPassword() {
        return view('auth.password-reset');
    }

    /**
     * Send an email to user with a temporary password.
     *
     * @param \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function tempPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ],
        [
            'email.required'  => "Veuillez saisir votre courriel",
            'email.email'     => "Veuillez saisir un courriel valide",
            'email.exists'    => "Ce courriel n'est pas associé à un compte existant"
        ]);

        if (User::where('email', $request->email)->exists()) {
            $user = User::where('email', $request->email)->get();
            $user = $user[0];
            $user_id=$user->id;
            $temp_password= str::random(25);
            $user->temp_password = $temp_password;
            $user->save();
            $to_name = $user->nom;
            $to_email = $request->email;
            $body="<a href='http://localhost:8000/new-password/".$user_id."/".$temp_password.
            "'>Cliquez ici pour réinitialiser votre mot de passe</a>";
            Mail::send('email.mail', ['name' => $to_name, 'body' => $body], function ($message) use ($to_name, $to_email) {
                $message->to($to_email, $to_name)->subject('Réinitialisation du mot de passe');
            });
            return redirect()->back()->withSuccess("Un mot de passe temporaire vous a été envoyé. Veuillez consulter vos courriels.");    
        }
        return redirect()->back()->withErrors("Ce courriel n'est pas associé à un compte existant");
    }

    /**
     * Display a form for the user to create a new password.
     *
     * @param \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function newPassword(User $user, $tempPassword)
    {
        if ($user->temp_password === $tempPassword) {
            return view ('auth.new-password');
        }
        return redirect('password.forgot')->withErrors("L'adresse courriel ou le mot de passe est incorrect");
    }

    public function storeNewPassword(User $user, $tempPassword, Request $request)
    {
        if ($user->temp_password === $tempPassword) {
            $request->validate([
                'password'    => 'required|min:6|confirmed|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{6,}$/',
            ],
            [
                'password.required'     => "Veuillez saisir votre nouveau mot de passe",
                'password.min'          => "Votre mot de passe doit contenir au moins 6 caractères",
                'password.regex'        => "Votre mot de passe doit contenir au moins une minuscule, une majuscule, un chiffre et un caractère spécial",
                'password.confirmed'    => "Les mots de passe ne correspondent pas"
            ]);
            $user->password = Hash::make($request->password);
            $user->temp_password = NULL;
            $user->save();
            return redirect('login')->withSuccess('Mot de passe modifié avec succès ');
        }
        return redirect('password.forgot')->withErrors('Le mot de passe temporaire ne correspond pas');
    }


    /**
     * Remove the user from storage.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Request  $user
     * @return \Illuminate\Http\Response
     */
    public function destroy(User $user, Request $request)
    {
        $request->validate([
            'password' => 'required',
        ], 
        [
            'password.required' => "Le mot de passe est requis pour supprimer un compte"
        ]);
    
        if (Hash::check($request->password, $user->password)) {
            
            $celliers = $user->celliers;
            foreach($celliers as $cellier){
                $cellier->bouteillesCelliers()->delete(); 
            }
            $user->celliers()->delete();
        
            $listes = $user->listes;
            foreach($listes as $liste){
                $liste->bouteillesListes()->delete(); 
            }
            $user->listes()->delete();

            $user->delete();
            auth()->logout();
            return view('welcome', ['successMessage' => 'Compte supprimé avec succès.']);
        } else {
            return back()->withErrors(['erreur' => 'Le mot de passe est incorrect.']);
        }
    }

}
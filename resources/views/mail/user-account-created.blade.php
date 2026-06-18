<h2>Your CPHL ICT Support Portal account is ready</h2>
<p>Hello {{ $user->name }},</p>
<p>An ICT user account has been created for you on the CPHL ICT Support Portal.</p>
<p><strong>Login URL:</strong> <a href="{{ url('/login') }}">{{ url('/login') }}</a></p>
<p><strong>Email Address:</strong> {{ $user->email }}</p>
<p><strong>Temporary Password:</strong> {{ $password }}</p>
<p>Please sign in and change your password after your first login.</p>

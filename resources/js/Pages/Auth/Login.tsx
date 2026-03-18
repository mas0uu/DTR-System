import InputError from '@/Components/InputError';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function Login({
    status,
    canResetPassword,
}: {
    status?: string;
    canResetPassword: boolean;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        credential: '',
        password: '',
        remember: false as boolean,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <>
            <Head title="Log in" />

            <div className="login-shell">
                <div className="login-layout">
                    <section className="login-visual" aria-hidden="true">
                        <div className="login-visual-art">
                            <img
                                src="/images/login-side.png"
                                alt="Login visual"
                                className="login-visual-image"
                            />
                            <div className="login-visual-fade" />
                        </div>
                    </section>

                    <section className="login-panel">
                        <div className="login-panel-inner">
                            <p className="login-brand">
                                <span>DTR & PAYROLL</span>
                            </p>

                            {status && <div className="login-status">{status}</div>}

                            <form onSubmit={submit} className="login-form">
                                <div className="login-field">
                                    <label htmlFor="credential" className="login-label">
                                        Email
                                    </label>
                                    <input
                                        id="credential"
                                        type="text"
                                        name="credential"
                                        value={data.credential}
                                        onChange={(e) => setData('credential', e.target.value)}
                                        placeholder="email"
                                        className="login-input"
                                        autoComplete="username"
                                        autoFocus
                                    />
                                    <InputError message={errors.credential} className="mt-2" />
                                </div>

                                <div className="login-field">
                                    <label htmlFor="password" className="login-label">
                                        Password
                                    </label>
                                    <input
                                        id="password"
                                        type="password"
                                        name="password"
                                        value={data.password}
                                        onChange={(e) => setData('password', e.target.value)}
                                        placeholder="password"
                                        className="login-input"
                                        autoComplete="current-password"
                                    />
                                    <InputError message={errors.password} className="mt-2" />
                                </div>

                                <div className="login-meta">
                                    <label className="login-remember">
                                        <input
                                            type="checkbox"
                                            checked={data.remember}
                                            onChange={(e) => setData('remember', e.target.checked)}
                                            className="login-checkbox"
                                        />
                                        <span>Remember me</span>
                                    </label>

                                    {canResetPassword && (
                                        <Link href={route('password.request')} className="login-link">
                                            Forgot your password?
                                        </Link>
                                    )}
                                </div>

                                <button type="submit" className="login-submit" disabled={processing}>
                                    {processing ? 'Logging in...' : 'Log in'}
                                </button>
                            </form>

                            <p className="login-footer-note">
                                Need access? Reach out to your system administrator.
                            </p>
                        </div>
                    </section>
                </div>
            </div>
        </>
    );
}

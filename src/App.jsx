import React, { useEffect, useMemo, useState } from 'react';

const navItems = [
  { id: 'home', label: 'Home' },
  { id: 'about', label: 'About' },
  { id: 'products', label: 'Products' },
  { id: 'blog', label: 'Blog' },
  { id: 'contact', label: 'Contact' },
  { id: 'login', label: 'Login' },
];

const heroSlides = [
  {
    title: 'Find a home that fits',
    text: 'Browse available rental homes and stay connected with the Nyumbani Homes team.',
  },
  {
    title: 'Rental records made easier',
    text: 'A practical foundation for managing tenants, houses, posts, payments, and communication.',
  },
  {
    title: 'Built for landlords and tenants',
    text: 'Keep property details clear, simple, and reachable from one responsive frontend.',
  },
];

const fallbackPosts = [
  {
    id: 1,
    title: 'Welcome to Nyumbani Homes',
    author: 'Admin',
    date: '2026-05-11',
    content:
      'The React frontend is ready. Connect the PHP backend and MySQL database to publish live posts from the existing admin panel.',
    comments_count: 0,
  },
];

function getRouteFromHash() {
  const hash = window.location.hash.replace(/^#\/?/, '');
  const route = hash.split('/')[0];
  return route || 'home';
}

function App() {
  const [route, setRoute] = useState(getRouteFromHash);
  const [selectedPostId, setSelectedPostId] = useState(null);

  useEffect(() => {
    const onHashChange = () => {
      const hash = window.location.hash.replace(/^#\/?/, '');
      const [nextRoute, maybeId] = hash.split('/');
      setRoute(nextRoute || 'home');
      setSelectedPostId(maybeId || null);
      window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    window.addEventListener('hashchange', onHashChange);
    onHashChange();
    return () => window.removeEventListener('hashchange', onHashChange);
  }, []);

  const page = useMemo(() => {
    if (route === 'about') return <About />;
    if (route === 'products') return <Products />;
    if (route === 'blog' && selectedPostId) return <BlogPost postId={selectedPostId} />;
    if (route === 'blog') return <Blog />;
    if (route === 'contact') return <Contact />;
    if (route === 'login') return <Login />;
    return <Home />;
  }, [route, selectedPostId]);

  const isHome = route === 'home';

  return (
    <>
      <Header activeRoute={route} isHome={isHome} />
      {isHome && <Hero />}
      <main>{page}</main>
      <Footer />
    </>
  );
}

function Header({ activeRoute, isHome }) {
  const [open, setOpen] = useState(false);

  return (
    <header className={isHome ? 'banner' : 'banner1'}>
      <div className="container">
        <div className="w3_agile_banner_top">
          <div className="agile_phone_mail">
            <ul>
              <li>
                <i className="fa fa-phone" aria-hidden="true" /> +(254) 123 456 789
              </li>
              <li>
                <i className="fa fa-envelope" aria-hidden="true" />
                <a href="mailto:info@nyumbanihomes.com">info@nyumbanihomes.com</a>
              </li>
            </ul>
          </div>
        </div>

        <div className="agileits_w3layouts_banner_nav">
          <nav className="navbar navbar-default">
            <div className="navbar-header navbar-left">
              <button
                type="button"
                className="navbar-toggle"
                aria-expanded={open}
                aria-label="Toggle navigation"
                onClick={() => setOpen((value) => !value)}
              >
                <span className="icon-bar" />
                <span className="icon-bar" />
                <span className="icon-bar" />
              </button>
              <h1>
                <a className="navbar-brand" href="#/home" onClick={() => setOpen(false)}>
                  <img src="/images/logo.png" className="img-responsive" alt="Nyumbani Homes" />
                </a>
              </h1>
            </div>

            <div className={`navbar-collapse navbar-right ${open ? 'in react-nav-open' : ''}`}>
              <nav className="cl-effect-13">
                <ul className="nav navbar-nav">
                  {navItems.map((item) => (
                    <li key={item.id} className={activeRoute === item.id ? 'active' : ''}>
                      <a href={`#/${item.id}`} onClick={() => setOpen(false)}>
                        {item.label}
                      </a>
                    </li>
                  ))}
                </ul>
              </nav>
            </div>
          </nav>
        </div>
      </div>
    </header>
  );
}

function Hero() {
  const [index, setIndex] = useState(0);

  useEffect(() => {
    const interval = window.setInterval(() => {
      setIndex((value) => (value + 1) % heroSlides.length);
    }, 4500);
    return () => window.clearInterval(interval);
  }, []);

  const slide = heroSlides[index];

  return (
    <section className="wthree_banner_info react-hero">
      <div className="container">
        <h3>{slide.title}</h3>
        <p>{slide.text}</p>
        <div className="hero-dots" aria-label="Hero slides">
          {heroSlides.map((item, slideIndex) => (
            <button
              key={item.title}
              type="button"
              className={slideIndex === index ? 'active' : ''}
              aria-label={`Show slide ${slideIndex + 1}`}
              onClick={() => setIndex(slideIndex)}
            />
          ))}
        </div>
      </div>
    </section>
  );
}

function Home() {
  return (
    <section className="process all_pad agileits">
      <div className="container">
        <div className="process_grids row">
          <div className="col-md-4 bottom-agileinfo bottom1">
            <h3>Vacant Houses</h3>
            <p>Display available rooms and homes from one public-facing site.</p>
            <i className="fa fa-arrow-circle-o-right" aria-hidden="true" />
          </div>
          <div className="col-md-4 bottom-w3-agileits bottom2">
            <h3>Tenant Records</h3>
            <p>Keep tenant and rental details organized through the existing admin panel.</p>
            <i className="fa fa-arrow-circle-o-right" aria-hidden="true" />
          </div>
          <div className="col-md-4 bottom-agile bottom3">
            <h3>Payments</h3>
            <p>Use the PHP backend foundation for payments, invoices, and receipts.</p>
            <i className="fa fa-arrow-circle-o-right" aria-hidden="true" />
          </div>
        </div>
      </div>
    </section>
  );
}

function About() {
  return (
    <>
      <section className="about-wthree">
        <h2 className="w3l_head w3l_head1">About Us</h2>
        <div className="furniture">
          <div className="container">
            <p className="lead react-copy">
              Nyumbani Homes is a rental house management system for landlords and property teams.
              This React frontend keeps the original public site layout while making the interface
              easier to maintain as components.
            </p>
          </div>
        </div>
      </section>

      <section className="statistics">
        <div className="container">
          <h3 className="w3l_head w3l_head1">Our Statistics</h3>
          <div className="statistics-grids row">
            {[
              ['190', 'Active users'],
              ['8', 'Partners'],
              ['10520', 'Raised Fundings'],
              ['2536', 'Questions'],
            ].map(([number, label]) => (
              <div className="col-md-3 col-sm-3 col-xs-6 statistics-grid" key={label}>
                <div className="counter numscroller">{number}</div>
                <h5>{label}</h5>
              </div>
            ))}
          </div>
        </div>
      </section>
    </>
  );
}

function Products() {
  const products = [
    ['Property Listings', 'Show available houses and rooms to prospective tenants.'],
    ['Tenant Management', 'Admit tenants and keep their records in the admin panel.'],
    ['Invoices and Payments', 'Track manual payments, receipts, and account balances.'],
  ];

  return (
    <section className="gallery">
      <div className="container">
        <h2 className="w3l_head w3l_head1">Our Products</h2>
        <div className="wthree_gallery_grids row react-card-grid">
          {products.map(([title, text]) => (
            <article className="col-md-4" key={title}>
              <div className="react-feature-card">
                <h4>{title}</h4>
                <p>{text}</p>
              </div>
            </article>
          ))}
        </div>
      </div>
    </section>
  );
}

function Blog() {
  const [posts, setPosts] = useState([]);
  const [status, setStatus] = useState('loading');

  useEffect(() => {
    fetch('/api/posts.php')
      .then((response) => {
        if (!response.ok) throw new Error('Posts endpoint unavailable');
        return response.json();
      })
      .then((data) => {
        setPosts(Array.isArray(data.posts) && data.posts.length ? data.posts : fallbackPosts);
        setStatus('ready');
      })
      .catch(() => {
        setPosts(fallbackPosts);
        setStatus('ready');
      });
  }, []);

  return (
    <section className="gallery">
      <div className="container">
        <h2 className="w3l_head w3l_head1">Blog</h2>
        <div className="wthree_gallery_grids">
          {status === 'loading' ? (
            <p className="react-copy">Loading posts...</p>
          ) : (
            <div className="row">
              {posts.map((post) => (
                <article className="col-md-4 react-post-preview" key={post.id}>
                  <a href={`#/blog/${post.id}`}>
                    <h4>{post.title}</h4>
                  </a>
                  <p>{post.content.slice(0, 200)}...</p>
                  <h6>
                    {post.author} <b>|</b> {post.date}
                  </h6>
                  <hr />
                </article>
              ))}
            </div>
          )}
        </div>
      </div>
    </section>
  );
}

function BlogPost({ postId }) {
  const [post, setPost] = useState(null);
  const [comments, setComments] = useState([]);

  useEffect(() => {
    fetch(`/api/post.php?id=${encodeURIComponent(postId)}`)
      .then((response) => {
        if (!response.ok) throw new Error('Post endpoint unavailable');
        return response.json();
      })
      .then((data) => {
        setPost(data.post || fallbackPosts[0]);
        setComments(Array.isArray(data.comments) ? data.comments : []);
      })
      .catch(() => {
        setPost(fallbackPosts[0]);
        setComments([]);
      });
  }, [postId]);

  if (!post) {
    return (
      <section className="gallery">
        <div className="container">
          <p className="react-copy">Loading post...</p>
        </div>
      </section>
    );
  }

  return (
    <section className="gallery">
      <div className="container">
        <h2 className="w3l_head w3l_head1">Blog Post</h2>
        <div className="wthree_gallery_grids">
          <a href="#/blog">
            <i className="fa fa-arrow-left" aria-hidden="true" /> Back
          </a>
          <h4 className="react-post-title">{post.title}</h4>
          <h6 className="react-post-meta">
            {post.author} <b>|</b> ({comments.length}) Comments <b>|</b> {post.date}
          </h6>
          <p className="react-copy">{post.content}</p>
          <hr />

          <h4>Comments ({comments.length})</h4>
          <div className="react-comments">
            {comments.length ? (
              comments.map((comment) => (
                <div key={`${comment.name}-${comment.date}`}>
                  <b>{comment.name}:</b>
                  <p>
                    {comment.comment}
                    <i>{comment.date}</i>
                  </p>
                  <hr />
                </div>
              ))
            ) : (
              <p>No comments yet.</p>
            )}
          </div>

          <h3>Leave a comment</h3>
          <div className="agileits_mail_grid_left col-md-9 react-comment-form">
            <form action="/functions/comment.php" method="post">
              <input type="hidden" name="blogid" value={post.id} />
              <input type="text" name="name" placeholder="Name..." required />
              <textarea placeholder="Comment..." name="comment" required />
              <input type="submit" value="Submit Comment" name="submit" />
            </form>
          </div>
        </div>
      </div>
    </section>
  );
}

function Contact() {
  return (
    <>
      <section className="mail">
        <div className="container">
          <h2 className="w3l_head w3l_head1">Contact Us</h2>
          <div className="agileits_mail_grids">
            <div className="agileits_mail_grid_left">
              <form action="/functions/contact.php" method="post">
                <h4>Your Names*</h4>
                <input type="text" name="names" placeholder="Names..." required />
                <h4>Your Email*</h4>
                <input type="email" name="email" placeholder="Email..." required />
                <h4>Your Message*</h4>
                <textarea placeholder="Message..." name="message" required />
                <input type="submit" name="submit" value="Send Message" />
              </form>
            </div>
          </div>
        </div>
      </section>
      <section className="w3l-map" aria-label="Company offices map">
        <iframe
          title="Nyumbani Homes office map"
          width="100%"
          height="300"
          src="https://maps.google.com/maps?width=100%&height=300&hl=en&q=Relaince%20center%20%2C%20Woodvale%20Grove%2C%20Westlands%20-%20Nairobi%2C%20Kenya+(Company%20Offices)&ie=UTF8&t=&z=15&iwloc=B&output=embed"
        />
      </section>
    </>
  );
}

function Login() {
  return (
    <section className="mail react-login-section">
      <div className="container">
        <h2 className="w3l_head w3l_head1">Admin Login</h2>
        <div className="agileits_mail_grids">
          <div className="agileits_mail_grid_left react-login-box">
            <form action="/admin/login.php" method="post">
              <h4>Email*</h4>
              <input type="email" name="email" placeholder="Email..." required />
              <h4>Password*</h4>
              <input type="password" name="password" placeholder="Password..." required />
              <input type="submit" name="submit" value="Log In" />
            </form>
            <p className="react-login-note">
              This uses the existing PHP admin session. Run the project through XAMPP or another
              PHP server for the login to reach <code>admin/login.php</code>.
            </p>
          </div>
        </div>
      </div>
    </section>
  );
}

function Footer() {
  return (
    <>
      <footer className="footer">
        <div className="col-md-4 footer-left-agileits">
          <h3>Address</h3>
          <ul>
            <li>
              <span className="glyphicon glyphicon-home" aria-hidden="true" />
              Woodvale Grove, Westlands - Nairobi, Kenya
            </li>
            <li>
              <span className="glyphicon glyphicon-envelope" aria-hidden="true" />
              <a href="mailto:info@example.com">info@example.com</a>
            </li>
          </ul>
        </div>
        <div className="col-md-4 footer-left-agileinfo">
          <h3>Get In Touch</h3>
          <p>Follow us, Tweet us, Tag us, Pin us.</p>
          <ul className="social-icons">
            <li>
              <a href="#" className="icon icon-border facebook" aria-label="Facebook" />
            </li>
            <li>
              <a href="#" className="icon icon-border twitter" aria-label="Twitter" />
            </li>
            <li>
              <a href="#" className="icon icon-border instagram" aria-label="Instagram" />
            </li>
            <li>
              <a href="#" className="icon icon-border pinterest" aria-label="Pinterest" />
            </li>
          </ul>
        </div>
        <div className="col-md-4 footer-left-w3-agileits">
          <h3>Newsletter</h3>
          <p>Subscribe to our newsletter and be the first to know what we are up to.</p>
          <form action="/functions/subscribe.php" method="post">
            <input type="email" name="email" placeholder="Your email..." required />
            <input type="submit" value=" " name="submit" aria-label="Subscribe" />
          </form>
        </div>
        <div className="clearfix" />
      </footer>
      <div className="copyright-w3-agile">
        <div className="container">
          <p>&copy; 2018 company | All rights reserved.</p>
        </div>
      </div>
    </>
  );
}

export default App;

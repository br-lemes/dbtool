--
-- PostgreSQL database dump
--

-- Dumped from database version 17.5
-- Dumped by pg_dump version 17.5

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: public; Type: SCHEMA; Schema: -; Owner: test_user
--

CREATE SCHEMA public;


ALTER SCHEMA public OWNER TO test_user;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: post_tags; Type: TABLE; Schema: public; Owner: test_user
--

CREATE TABLE public.post_tags (
    post_id bigint NOT NULL,
    tag_id integer NOT NULL,
    refresh_at timestamp without time zone
);


ALTER TABLE public.post_tags OWNER TO test_user;

--
-- Name: posts; Type: TABLE; Schema: public; Owner: test_user
--

CREATE TABLE public.posts (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    content text,
    publish_date date,
    title character varying(200) NOT NULL,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


ALTER TABLE public.posts OWNER TO test_user;

--
-- Name: posts_id_seq; Type: SEQUENCE; Schema: public; Owner: test_user
--

CREATE SEQUENCE public.posts_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.posts_id_seq OWNER TO test_user;

--
-- Name: posts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: test_user
--

ALTER SEQUENCE public.posts_id_seq OWNED BY public.posts.id;


--
-- Name: products; Type: TABLE; Schema: public; Owner: test_user
--

CREATE TABLE public.products (
    id integer NOT NULL,
    description_long text,
    description_medium text,
    description_tiny text,
    ean character varying(100) NOT NULL,
    name character varying(255) NOT NULL,
    price numeric(10,2) DEFAULT 0.00,
    sku character varying(100) NOT NULL,
    status character varying(50) DEFAULT 'active'::character varying,
    stock integer DEFAULT 0,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    refresh_at timestamp without time zone
);


ALTER TABLE public.products OWNER TO test_user;

--
-- Name: products_id_seq; Type: SEQUENCE; Schema: public; Owner: test_user
--

CREATE SEQUENCE public.products_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.products_id_seq OWNER TO test_user;

--
-- Name: products_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: test_user
--

ALTER SEQUENCE public.products_id_seq OWNED BY public.products.id;


--
-- Name: tags; Type: TABLE; Schema: public; Owner: test_user
--

CREATE TABLE public.tags (
    id integer,
    description text,
    name character varying(100)
);


ALTER TABLE public.tags OWNER TO test_user;

--
-- Name: user_groups; Type: TABLE; Schema: public; Owner: test_user
--

CREATE TABLE public.user_groups (
    id integer NOT NULL,
    user_id bigint NOT NULL,
    updated_at timestamp without time zone,
    key_id integer
);


ALTER TABLE public.user_groups OWNER TO test_user;

--
-- Name: users; Type: TABLE; Schema: public; Owner: test_user
--

CREATE TABLE public.users (
    id bigint NOT NULL,
    email character varying(255) NOT NULL,
    name character varying(100) NOT NULL,
    password_hash text NOT NULL,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp without time zone
);


ALTER TABLE public.users OWNER TO test_user;

--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: test_user
--

CREATE SEQUENCE public.users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.users_id_seq OWNER TO test_user;

--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: test_user
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- Name: posts id; Type: DEFAULT; Schema: public; Owner: test_user
--

ALTER TABLE ONLY public.posts ALTER COLUMN id SET DEFAULT nextval('public.posts_id_seq'::regclass);


--
-- Name: products id; Type: DEFAULT; Schema: public; Owner: test_user
--

ALTER TABLE ONLY public.products ALTER COLUMN id SET DEFAULT nextval('public.products_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: test_user
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Name: post_tags post_tags_post_tag; Type: CONSTRAINT; Schema: public; Owner: test_user
--

ALTER TABLE ONLY public.post_tags
    ADD CONSTRAINT post_tags_post_tag UNIQUE (post_id, tag_id);


--
-- Name: posts posts_pkey; Type: CONSTRAINT; Schema: public; Owner: test_user
--

ALTER TABLE ONLY public.posts
    ADD CONSTRAINT posts_pkey PRIMARY KEY (id);


--
-- Name: products products_ean_sku_key; Type: CONSTRAINT; Schema: public; Owner: test_user
--

ALTER TABLE ONLY public.products
    ADD CONSTRAINT products_ean_sku_key UNIQUE (ean, sku);


--
-- Name: products products_pkey; Type: CONSTRAINT; Schema: public; Owner: test_user
--

ALTER TABLE ONLY public.products
    ADD CONSTRAINT products_pkey PRIMARY KEY (id);


--
-- Name: user_groups user_groups_pkey; Type: CONSTRAINT; Schema: public; Owner: test_user
--

ALTER TABLE ONLY public.user_groups
    ADD CONSTRAINT user_groups_pkey PRIMARY KEY (id, user_id);


--
-- Name: users users_email_key; Type: CONSTRAINT; Schema: public; Owner: test_user
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_key UNIQUE (email);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: test_user
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: posts_user_id; Type: INDEX; Schema: public; Owner: test_user
--

CREATE INDEX posts_user_id ON public.posts USING btree (user_id);


--
-- Name: user_groups_key_id; Type: INDEX; Schema: public; Owner: test_user
--

CREATE INDEX user_groups_key_id ON public.user_groups USING btree (key_id);


--
-- Name: SCHEMA public; Type: ACL; Schema: -; Owner: test_user
--

REVOKE USAGE ON SCHEMA public FROM PUBLIC;


--
-- PostgreSQL database dump complete
--


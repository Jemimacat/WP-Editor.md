<?php

namespace EditormdFront;

use EditormdApp\WPComMarkdown;

class Controller {
	/**
	 * @var string 插件名称
	 */
	private $plugin_name;

	/**
	 * @var string 插件版本号
	 */
	private $version;

	/**
	 * @var string 翻译文本域
	 */
	private $text_domain;

	private $front_static_url;
	/**
	 * Controller constructor 初始化类并设置其属性
	 *
	 * @param $plugin_name
	 * @param $version
	 * @param $ioption
	 */
	public function __construct( $plugin_name, $version, $text_domain ) {

		$this->plugin_name = $plugin_name;
		$this->text_domain = $text_domain;
		$this->version     = $version;
		//$this->front_static_url = '//cdn.jsdelivr.net/wp/wp-editormd/trunk';
		$this->front_static_url = '//wordpress.test/wp-content/plugins/WP-Editor.md/';

		add_filter( 'quicktags_settings', array( $this, 'quicktags_settings' ), 'content' );

		add_action( 'admin_init', array( $this, 'editormd_markdown_posting_always_on' ), 11 );

		// 如果模块是激活状态保持文章/页面正常激活，评论Markdown是可选
		add_filter( 'pre_option_' . WPComMarkdown::POST_OPTION, '__return_true' );
	}

	/**
	 * 注册样式文件
	 */
	public function enqueue_front_styles() {
		//Style - Editor.md
		wp_enqueue_style( 'Editormd_Front', $this->front_static_url . '/assets/Editormd/editormd.min.css', array(), '2.0.1', 'all' );
		//Style - Config
		wp_enqueue_style( 'Config_Front', $this->front_static_url . '/assets/Config/editormd.min.css', array(), $this->version, 'all' );
	}

	/**
	 * 注册脚本文件
	 */
	public function enqueue_front_scripts() {
		//JavaScript - jQuery
		wp_enqueue_script( 'jQuery-CDN', $this->front_static_url . '/npm/jquery@1.12.4/dist/jquery.min.js', array(), '1.12.4', true );
		//JavaScript - Editormd
		wp_enqueue_script( 'Editormd_Front', $this->front_static_url . '/assets/Editormd/editormd.min.js', array( 'jQuery-CDN' ), '2.0.1', true );
		//JavaScript - Config
		wp_enqueue_script( 'Config_Front', $this->front_static_url . '/assets/Config/editormd.min.js', array( 'Editormd_Front' ), $this->version, true );

		//JavaScript - 载入国际化语言资源文件
		$lang = get_bloginfo( 'language' );
		switch ( $lang ) {
			case 'zh-TW':
				wp_enqueue_script( 'Editormd-lang-tw_Front', $this->front_static_url . '/assets/Editormd/languages/zh-tw.js', array(), '2.0.1', true );//载入台湾语言资源库
				break;
			case 'zh-CN':
				break;
			case 'en-US':
				wp_enqueue_script( 'Editormd-lang-us_Front', $this->front_static_url . '/assets/Editormd/languages/en.js', array(), '2.0.1', true );//载入美国英语语言资源库
				break;
			default:
				wp_enqueue_script( 'Editormd-lang-us_Front', $this->front_static_url . '/assets/Editormd/languages/en.js', array(), '2.0.1', true );//默认载入美国英语语言资源库
		}


		if ( $this->get_option( 'highlight_library_style', 'syntax_highlighting' ) == 'customize' ) {
			$prismTheme = 'default';
		} else {
			$prismTheme = $this->get_option( 'highlight_library_style', 'syntax_highlighting' );
		}

		wp_localize_script( 'Config_Front', 'Editormd', array(
			'editormdUrl'       => $this->front_static_url,
			'syncScrolling'     => $this->get_option( 'sync_scrolling', 'editor_basics' ), //编辑器同步
			'watch'             => $this->get_option( 'live_preview', 'editor_basics' ), //即是否开启实时预览
			'htmlDecode'        => $this->get_option( 'html_decode', 'editor_basics' ), //HTML标签解析
			'toc'               => $this->get_option( 'support_toc', 'editor_toc' ), //TOC
			'theme'             => $this->get_option( 'theme_style', 'editor_style' ), //编辑器总体主题
			'previewTheme'      => $this->get_option( 'theme_style', 'editor_style' ), //编辑器预览主题
			'editorTheme'       => $this->get_option( 'code_style', 'editor_style' ), //编辑器编辑主题
			'emoji'             => $this->get_option( 'support_emoji', 'editor_emoji' ), //emoji表情
			'tex'               => $this->get_option( 'support_katex', 'editor_katex' ), //科学公式
			'taskList'          => $this->get_option( 'task_list', 'editor_basics' ), //task lists
			'imagePaste'        => $this->get_option( 'imagepaste', 'editor_basics' ), //图像粘贴
			'imagePasteSM'      => $this->get_option( 'imagepaste_sm', 'editor_basics' ), //图像粘贴上传源
			'staticFileCDN'     => $this->get_option( 'static_cdn', 'editor_basics' ), //静态资源CDN地址
			'prismTheme'        => $prismTheme, //语法高亮风格
			'prismLineNumbers'  => $this->get_option( 'line_numbers', 'syntax_highlighting' ), //行号显示
			'mindMap'           => $this->get_option( 'support_mindmap', 'editor_mindmap' ), //思维导图
			'mermaid'           => $this->get_option('support_mermaid','editor_mermaid'), // Mermaid
			//'mermaidConfig'     => $this->get_option('mermaid_config','editor_mermaid'), // Mermaid配置
			'placeholderEditor' => __( 'Enjoy Markdown! Coding now...', $this->text_domain ),
			'imgUploading'      => __( 'Image Uploading...', $this->text_domain ),
			'imgUploadeFailed'  => __( 'Failed To Upload The Image!', $this->text_domain ),
			'supportComment'   => $this->get_option('support_front','editor_basics'), // 前端评论
		) );
	}


	/**
	 * 将 Jetpack Markdown写作模式始终设置为开
	 */
	public function editormd_markdown_posting_always_on() {
		if ( ! class_exists( 'WPComMarkdown' ) ) {
			return;
		}
		global $wp_settings_fields;
		if ( isset( $wp_settings_fields['writing']['default'][ WPComMarkdown::POST_OPTION ] ) ) {
			unset( $wp_settings_fields['writing']['default'][ WPComMarkdown::POST_OPTION ] );
		}
	}

	/**
	 * 快速标记按钮
	 *
	 * @param $qt_init
	 *
	 * @return mixed
	 */
	public function quicktags_settings( $qt_init ) {

		$qt_init['buttons'] = '';

		return $qt_init;
	}

	/**
	 * 获取字段值
	 *
	 * @param string $option 字段名称
	 * @param string $section 字段名称分组
	 * @param string $default 没搜索到返回空
	 *
	 * @return mixed
	 */
	public function get_option( $option, $section, $default = '' ) {

		$options = get_option( $section );

		if ( isset( $options[ $option ] ) ) {
			return $options[ $option ];
		}

		return $default;
	}

}

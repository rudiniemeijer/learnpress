<?php

/**
 * LP_Question_True_Or_False
 *
 * @author  ThimPress
 * @package LearnPress/Templates
 * @version 1.0
 * @extends LP_Question
 */

defined( 'ABSPATH' ) || exit();

class LP_Question_True_Or_False extends LP_Question {
	protected $_type = 'true_or_false';

	/**
	 * Constructor
	 *
	 * @param null $the_question
	 * @param null $args
	 */
	public function __construct( $the_question = null, $args = null ) {
		//add_filter( 'learn_press_question_answers', array( $this, 'limit_answers' ), 10, 2 );
		if ( ! has_filter( 'learn-press/question/load-answer-options' ) ) {
			add_filter( 'learn-press/question/load-answer-options', array( $this, 'validate_answer_options' ), 10, 2 );
		}
		parent::__construct( $the_question, $args );
	}

	/**
	 * Validate answer options for this question type.
	 * This question should have 2 options in any case.
	 *
	 * @param array $answer_options
	 * @param int   $id
	 *
	 * @return array
	 */
	public function validate_answer_options( $answer_options, $id ) {

		remove_filter( 'learn-press/question/load-answer-options', array(
			$this,
			'validate_answer_options'
		), 10, 2 );
		if ( get_post_meta( $id, '_lp_type', true ) == $this->get_type() ) {
			$size_of_options = $answer_options ? sizeof( $answer_options ) : 0;
			switch ( $size_of_options ) {
				case 0:
				case 1:
					settype( $answer_options, 'array' );
					$answer_options = array_filter( $answer_options );
					for ( $n = 2 - $size_of_options, $i = 0; $i < $n; $i ++ ) {
						$answer_options[] = apply_filters(
							'learn-press/question/default-answer-option-data',
							array(
								'text'               => '',
								'value'              => learn_press_uniqid(),
								'answer_order'       => $i + 1,
								'question_answer_id' => - 1 //fake id
							),
							$id
						);
					}
					break;
				case 2:
					// Great! Do nothing here
					break;
				default:
					$temp           = $answer_options;
					$answer_options = array();
					foreach ( $temp as $k => $v ) {
						$answer_options[ $k ] = $v;
						if ( sizeof( $answer_options ) == 2 ) {
							break;
						}
					}
			}
		}

		return $answer_options;
	}

	public function limit_answers( $answers = array(), $question ) {
		if ( $question->type == $this->type ) {
			$answers = array_splice( $answers, 0, 2 );
		}

		return $answers;
	}

	public function save( $post_data = null ) {
		parent::save( $post_data );
	}

	public function submit_answer( $quiz_id, $answer ) {
		$questions = learn_press_get_question_answers( null, $quiz_id );
		if ( ! is_array( $questions ) ) {
			$questions = array();
		}
		$questions[ $quiz_id ][ $this->get( 'ID' ) ] = is_array( $answer ) ? reset( $answer ) : $answer;
		learn_press_save_question_answer( null, $quiz_id, $this->get( 'ID' ), is_array( $answer ) ? reset( $answer ) : $answer );
	}

	public function get_default_answers( $answers = false ) {
		if ( ! $answers ) {
			if ( $this->id && $this->post->post_status !== 'auto-draft' ) {
				global $wpdb;
				$sql              = $wpdb->prepare( "SELECT * FROM $wpdb->learnpress_question_answers "
				                                    . " WHERE question_id = %d"
				                                    . " ORDER BY `answer_order`", $this->id );
				$question_answers = $wpdb->get_results( $sql );
				$answers          = array();
				foreach ( $question_answers as $qa ) {
					$answers[] = unserialize( $qa->answer_data );
				}
			}
			if ( ! empty( $answers ) ) {
				return $answers;
			}
			$answers = array(
				array(
					'is_true' => 'yes',
					'value'   => 'true',
					'text'    => __( 'True', 'learnpress' )
				),
				array(
					'is_true' => 'no',
					'value'   => 'false',
					'text'    => __( 'False', 'learnpress' )
				)
			);
		}

		return $answers;
	}

	public function admin_interface( $args = array() ) {
		return parent::admin_interface( $args );
	}

	public function render( $args = array() ) {
		$args     = wp_parse_args(
			$args,
			array(
				'answered'   => null,
				'history_id' => 0,
				'quiz_id'    => 0,
				'course_id'  => 0
			)
		);
		$answered = ! empty( $args['answered'] ) ? $args['answered'] : null;
		if ( null === $answered ) {
			$answered = $this->get_user_answered( $args );
		}
		$view = learn_press_locate_template( 'content-question/single-choice/answer-options.php' );
		include $view;
	}

	public function save_post_action() {

		if ( $post_id = $this->get( 'ID' ) ) {
			$post_data    = isset( $_POST[ LP_QUESTION_CPT ] ) ? $_POST[ LP_QUESTION_CPT ] : array();
			$post_answers = array();
			$post_explain = $post_data[ $post_id ]['explaination'];
			if ( isset( $post_data[ $post_id ] ) && $post_data = $post_data[ $post_id ] ) {

				//if( LP_QUESTION_CPT != get_post_type( $post_id ) ){
				try {
					$ppp = wp_update_post(
						array(
							'ID'         => $post_id,
							'post_title' => $post_data['text'],
							'post_type'  => LP_QUESTION_CPT
						)
					);
				}
				catch ( Exception $ex ) {
					echo "ex:";
					print_r( $ex );
				}

				// }else{

				// }

				$index = 0;

				foreach ( $post_data['answer']['text'] as $k => $txt ) {
					$post_answers[ $index ++ ] = array(
						'text'    => $txt,
						'is_true' => $post_data['answer']['is_true'][ $k ]
					);
				}

			}
			$post_data['answer']       = $post_answers;
			$post_data['type']         = $this->get_type();
			$post_data['explaination'] = $post_explain;
			update_post_meta( $post_id, '_lpr_question', $post_data );
			//print_r($post_data);
		}

		return $post_id;
		// die();
	}

	public function check( $user_answer = null ) {
		$return = array(
			'correct' => false,
			'mark'    => 0
		);
		if ( $answers = $this->answers ) {
			foreach ( $answers as $k => $answer ) {
				if ( ( $answer['is_true'] == 'yes' ) && ( $answer['value'] == $user_answer ) ) {
					$return['correct'] = true;
					$return['mark']    = floatval( $this->mark );
					break;
				}
			}
		}

		return $return;
	}

	/**
	 * Print js template
	 *
	 * @param string $args
	 *
	 * @return mixed
	 */
	public static function admin_js_template( $args = '' ) {
		$args = wp_parse_args( $args, array( 'echo' => true, 'type' => 'true_or_false' ) );
		parent::admin_js_template( $args );
	}
}
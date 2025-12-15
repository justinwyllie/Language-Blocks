import Form from 'react-bootstrap/Form';
import useState from 'react';
import Row from 'react-bootstrap/Row';
import Col from 'react-bootstrap/Col';
import Alert from 'react-bootstrap/Alert';
import { useSelect } from '@wordpress/data';
import { useRef } from '@wordpress/element';
import { useEntityProp } from '@wordpress/core-data';
import { Formik, FieldArray , useFormikContext } from 'formik';
import { Instruction, MultipleChoiceQuestion, LinkPanel, AuthorPanel} from './components';
import { LABELS } from '../translations';
import { useBlockProps, RichText } from '@wordpress/block-editor'; 
import _ from 'underscore';

const settings = window.kea_language_blocks.settings;
import { useDispatch } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor'; // ← Missing import
const POST_TITLE_LENGTH = window.kea_language_blocks.settings.page_title_length;


const MultipleChoice = ({postType, setAttributes, attributes}) =>
{

    const [ meta, setMeta ] = useEntityProp( 'postType', postType, 'meta' ); 
    const initialValues = {}; 
    initialValues.set = false;
    const defaultLang = 'en';
    const additionalLangs = ['ru'];
    const supportedLangs = [defaultLang, ...additionalLangs];
    const metaFieldValue = meta[ '_kea_activity_meta' ]; 
  
   
    let formSubmitting = false;
    const setFormSubmitting = (flag) =>
    {
        formSubmitting  = flag; 
    }

   

    const { lockPostSaving, unlockPostSaving } = useDispatch(editorStore);
    
    const DataLifter = ({ setAttributes }) =>
    {
        //every time form renders which s every time a value changes 
        const { values, errors } = useFormikContext();
        console.log("errors IS2", errors);
 
        if (Object.keys(errors).length > 0)
        {
            lockPostSaving('activities/activity-multiple-choice');
        }
        else
        {
            setAttributes({formData: values});   
            setAttributes({ activityType: 'multiplechoice'});
            unlockPostSaving('activities/activity-multiple-choice');
        }
 
    }

    const { postTitle } = useSelect(
        ( select ) => ( {
            postTitle: select('core/editor').getEditedPostAttribute('title')
        } ),
        []
    );
    const alertRef = useRef();




    const grammarTaxonomy =  useSelect(
        ( select ) => wp.data.select('core').getEntityRecords('taxonomy', "grammar", {per_page: 1000, context: "view", call: 'kea'}) 

    ); 

   const russianGrammarTaxonomy =  useSelect(
    ( select ) => wp.data.select('core').getEntityRecords('taxonomy', "russian_grammar", {per_page: 1000, context: "view", call: 'kea'})
                 
    );

    const terms = [];
    let userLabels = [];
 

    if (grammarTaxonomy != undefined) {
        grammarTaxonomy.forEach((item => {
            terms[item.id] = item.name;
        }));
    }

    if (russianGrammarTaxonomy != undefined) {
        russianGrammarTaxonomy.forEach((item => {
            terms[item.id] = item.name;
        }))
    }

    //detect user changing taxonomy terms
    //i think this weill get the latest unsaved values    
    const [grammarTerms, setGammarTerms] = useEntityProp( 'postType', postType, 'grammar_terms' ); 
    const [russianGrammarTerms, setRussianGrammarTerms] = useEntityProp( 'postType', postType, 'russian_grammar_terms' ); 
  


    if (grammarTerms != undefined)
    {
        grammarTerms.forEach((item) => {
            userLabels.push(terms[item]);
        });
    }
    
    if (russianGrammarTerms != undefined)
    {
        russianGrammarTerms.forEach((item) => {
            userLabels.push(terms[item]);
        });
    }
    

 
 
    
    const blockProps = useBlockProps();//? gets props passed to this 'edit' component?

    const ErrorMessage = ({postTitle}) =>
    {
        const { isValid } = useFormikContext();
      
        if (isValid && (postTitle.length >= POST_TITLE_LENGTH))
        {   
            return <></>
        }
        else
        {
            if (postTitle.length < POST_TITLE_LENGTH)
            {
                alertRef.current?.closest(".block-editor-writing-flow").scrollIntoView({ behavior: 'smooth' }); //TODO maybe hanlde no element found?
            }
            return <Alert variant="danger">{LABELS[settings.defaultUserLang]['please_check_the_form']['nominative']}</Alert>;
        }
    };
    
    function setInitialValues() {
            
            
        console.log("INIT ATTR", attributes);
        //this is case of new activity?
        if (Object.keys(attributes['formData']).length === 0)
        {
        
            initialValues.legacyName = '';
            initialValues.models = '';
            initialValues.explanation = '';
            initialValues.questions = [];
            initialValues.instructions = [];
            for (const lang of supportedLangs)
            {
                initialValues.instructions.push({lang: lang, text: ''});
            }
            
        }
        else //set initialValues for form based on XML string loaded from postmeta
        {
            const currentFormData = attributes['formData'];
            for (var field in currentFormData)
            {
                initialValues[field] = currentFormData[field];
            }
            
        }
        
        initialValues.set = true;
    }

    if (!initialValues.set)
    {
        setInitialValues();
    }
    


    return <div ref={alertRef}>
        {(postTitle.length < POST_TITLE_LENGTH) ? <div className="field-error">{LABELS[settings.defaultUserLang]['minimum_length_title']['nominative']}</div> : ''}  
            
        <Formik
            initialValues={initialValues}

            validate={values => {
                
                
                if (formSubmitting == true)
                {
                    return {};
                }
                
                let errors = {};
            
                /*
                if (values.ageGroup == 0) {
                    errors.ageGroup = "Required"; 
                }
                if (values.level == 0) {
                    errors.level = "Required"; 
                }
                */
        
                //TODO could valdiate for correct format ___ and |
            
                //https://formik.org/docs/guides/arrays
                //todo how to do my array of questions https://formik.org/docs/api/fieldarray

                if (settings.site == "repititor")
                {
                    let instructionsError = true;
                    let instructionsCount = 0;
                    values.instructions.forEach((item, idx) =>
                    {
                        
                        if (item.text != '')
                        {
                            instructionsError = false;
                        }

                        instructionsCount = idx;
                    });

                    if (instructionsError) {
                        errors.instructions = new Array();
                        errors.instructions[instructionsCount] = {"lang": '', "text": "At least one language must have instructions"};
                    }
                }
                else
                {
                    values.instructions.forEach((item, idx) =>
                    {
                        if (item.lang == defaultLang)
                        {
                            if (item.text == '')
                            {
                                errors.instructions = new Array();
                                errors.instructions[idx] = {"lang": '', "text": "Required"};
                            }
                        }
                    });

                }
            
                
            
                //let errorObj = {"question": '', "answers": []};
                values.questions.forEach((item, idx) =>
                {
                    
                    
                    //questions
                    if (values.questions[idx].question == '')
                    {
                        if (errors.questions == undefined)
                        {
                            errors.questions = new Array();
                        }
                        if (errors.questions[idx] == undefined)
                        {
                            errors.questions[idx] = {question: '', answers: []};
                        }
                    
                        errors.questions[idx].question = "Required";
                    }
                    

                    //answers
                    for (let i = 0;  i < 4; i++)
                        {
                            if (values.questions[idx].answers[i] == undefined || values.questions[idx].answers[i] == '' )
                            {
                                if (errors.questions == undefined)
                                {
                                    errors.questions = new Array();
                                }
                                if (errors.questions[idx] == undefined)
                                {
                                    errors.questions[idx] = {question: '', answers: []};
                                }
                                errors.questions[idx].answers[i] = "Please enter a value";
                            }
                            
                    }

                    
                })
             
                return errors;
            }}
            
            onSubmit={() => {
        
                
            }}
                
        >
            {({
                values,
                errors,
                touched,
                handleChange,
                setFieldValue,
                handleBlur,
                handleSubmit,
                isSubmitting,
                setFieldTouched,
                setTouched,
                validateForm,
                isValid
        
            }) => (
            
        
                
            <Form  onSubmit={(e) => {

                setFormSubmitting(true);
                handleSubmit(e)
                }
                } name="activty" id="activity" className="">

            
                <ErrorMessage postTitle={postTitle}></ErrorMessage>

                <DataLifter  setAttributes={setAttributes} />


                <Form.Group as={Row}>
                    <Form.Label column sm={2}>Legacy Name (optional)</Form.Label>
                    <Col md={10}>
                        <Form.Control md={10} name="legacyName" id="legacyName"
                            onChange={handleChange}
                            onBlur={handleBlur}
                            value={values.legacyName}
                            isInvalid={!!errors.legacyName && !!touched.legacyName}
                        ></Form.Control>
                        {errors.legacyName && touched.legacyName ? 
                            <div className="invalid-feedback">
                            {errors.legacyName}
                            </div> : null
                        }
                    </Col>
                </Form.Group>


                <Form.Group as={Row}> 
                        <Col>
                            <h3>Instructions</h3>
                        </Col>
                </Form.Group>        

                <div>     
                    {values.instructions.length > 0 && values.instructions.map((instruction, idx) =>           
                                <Instruction instruction={instruction} idx={idx} />
                    )}     
                </div>

                <Form.Group as={Row}> 
                        <Col>
                            <h3>Models</h3>
                        </Col>
                </Form.Group>   

                <Form.Group as={Row}>
                    <Col md={2}>
                        
                    </Col>
                    <Col md={10}>
                        
                        
                        <RichText name="models" id="models"
                            className="rich-input-control mt-3"
                            tagName="div" 
                            value={ values.models } 
                            allowedFormats={ [ 'core/bold', 'core/italic' ] } 
                            onChange={ ( content ) => {
                                setFieldValue("models", content);
                            } } 
                            
                        />

                    </Col>
                </Form.Group>

                <Form.Group as={Row}> 
                        <Col>
                            <h3>Explanation</h3>
                        </Col>
                </Form.Group>   

                <Form.Group as={Row}>
                    <Col md={2}>
                        
                    </Col>
                    <Col md={10}>
                        
                        
                        <RichText name="explanation" id="explanation"
                            className="rich-input-control mt-3"
                            tagName="div" 
                            value={ values.explanation } 
                            allowedFormats={ [ 'core/bold', 'core/italic' ] } 
                            onChange={ ( content ) => {
                                setFieldValue("explanation", content);
                            } } 
                            
                        />

                    </Col>
                </Form.Group>



                <Row>
                    <Col>
                        <h3>Questions</h3>
                        <p>Write the question in the question box. Then put 4 variants in the 4 answer boxes. The first box should contain the <span class="font-italic">correct</span> variant. 
                        The order of the answers in the drop-down presented to the user will be randomised. 
                            </p>
                    </Col>
                </Row>

                <FieldArray name="questions" validateOnChange={true}>
                {({ insert, remove, push }) => (
                    <div>     
                        {values.questions.length > 0 && values.questions.map( (item, idx) =>            
                            <MultipleChoiceQuestion idx={idx} 
                                insert={insert}
                                remove={remove}
                                touched={touched} 
                                >
                            </MultipleChoiceQuestion>)
                        }
                        <div className="text-right margin-top-10">
                            <button
                                className="secondary btn btn-primary"
                                type="button"
                                onClick={() => push({ question: '', answers: [] })}>
                            +
                            </button>
                        </div>
                    </div>
                )}
                </FieldArray>
                
                <Form.Group as={Row}>
                    <Col md={12}>
                            {/*<Button onClick={() => addQuestion() }>+</Button>*/}
                    </Col>
                </Form.Group>

                <Form.Group as={Row}>
                    <Col sm={{ span: 10, offset: 0 }}>
                        <div className="px-1 py-1 mt-3 mb-3">
                            {userLabels.map((item, i) => {
                                return <span className="badge rounded-pill bg-info text-dark me-2" key={i}>{item}</span>
                            })}
                        </div>
                    </Col>
                </Form.Group> 
                        
            </Form>
            )}
        </Formik>
   

</div>
    
}


export {

    MultipleChoice
}

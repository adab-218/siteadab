import React, { useState } from 'react';
import { Menu, X, ChevronRight, Users, Heart, BookOpen, HandHeart, MapPin, Mail, Facebook, Sparkles, Calendar } from 'lucide-react';
import WeeklySchedule from './components/WeeklySchedule';

interface MenuItemProps {
  href: string;
  children: React.ReactNode;
  onClick?: () => void;
}

const MenuItem: React.FC<MenuItemProps> = ({ href, children, onClick }) => (
  <a 
    href={href} 
    onClick={(e) => {
      e.preventDefault();
      onClick?.();
      document.querySelector(href)?.scrollIntoView({ behavior: 'smooth' });
    }}
    className="text-gray-700 hover:text-red-600 transition-colors duration-200 font-medium"
  >
    {children}
  </a>
);

interface ProgramCard {
  title: string;
  schedule: string;
  description: string;
  icon: React.ReactNode;
}

const ProgramCard: React.FC<ProgramCard> = ({ title, schedule, description, icon }) => (
  <div className="bg-white p-6 rounded-lg shadow-lg hover:shadow-xl transition-shadow duration-300">
    <div className="text-red-600 mb-4 flex justify-center">
      {icon}
    </div>
    <h3 className="text-xl font-bold text-gray-800 mb-2 text-center">{title}</h3>
    <p className="text-red-600 font-semibold text-center mb-3">{schedule}</p>
    <p className="text-gray-600 text-center leading-relaxed">{description}</p>
  </div>
);

interface DepartmentCard {
  name: string;
  description: string;
  icon: React.ReactNode;
}

const DepartmentCard: React.FC<DepartmentCard> = ({ name, description, icon }) => (
  <div className="bg-white p-6 rounded-lg shadow-lg hover:shadow-xl transition-all duration-300 hover:-translate-y-1">
    <div className="text-red-600 mb-4 flex justify-center text-3xl">
      {icon}
    </div>
    <h3 className="text-lg font-bold text-gray-800 mb-3 text-center">{name}</h3>
    <p className="text-gray-600 text-center leading-relaxed text-sm">{description}</p>
  </div>
);

function App() {
  const [isMenuOpen, setIsMenuOpen] = useState(false);
  const [showSchedule, setShowSchedule] = useState(false);
  const [prayerTopic, setPrayerTopic] = useState('');
  const [prayerResult, setPrayerResult] = useState('');
  const [reflectionResult, setReflectionResult] = useState('');
  const [isPrayerLoading, setIsPrayerLoading] = useState(false);
  const [isReflectionLoading, setIsReflectionLoading] = useState(false);

  const toggleMenu = () => setIsMenuOpen(!isMenuOpen);
  
  const closeMenu = () => setIsMenuOpen(false);

  const generatePrayer = async () => {
    if (!prayerTopic.trim()) return;
    
    setIsPrayerLoading(true);
    // Simulação de chamada API - substitua pela integração real com LLM
    setTimeout(() => {
      setPrayerResult(`Querido Pai Celestial, venho até Vós com um coração grato para falar sobre ${prayerTopic}. 

Sei que Tu és fiel e que todas as coisas cooperam para o bem daqueles que Te amam. Peço que me conceda sabedoria e discernimento neste momento, e que a Tua vontade seja feita em minha vida.

Que eu possa confiar plenamente em Ti, sabendo que Teus planos são sempre perfeitos. Obrigado(a) por me ouvir e por Teu amor incondicional.

Em nome de Jesus, amém.`);
      setIsPrayerLoading(false);
    }, 2000);
  };

  const generateReflection = async () => {
    setIsReflectionLoading(true);
    const today = new Date().toLocaleDateString('pt-BR');
    
    // Simulação de chamada API - substitua pela integração real com LLM
    setTimeout(() => {
      setReflectionResult(`**"Porque eu sei os pensamentos que tenho a respeito de vós, diz o SENHOR; pensamentos de paz, e não de mal, para vos dar o fim que esperais." - Jeremias 29:11**

Hoje, ${today}, somos lembrados de que Deus tem um plano perfeito para cada um de nós. Mesmo quando enfrentamos dificuldades ou incertezas, podemos descansar na certeza de que o Senhor conhece nosso futuro e tem preparado coisas boas para aqueles que O amam.

Nas tempestades da vida, quando tudo parece incerto, lembre-se: você não está sozinho. O mesmo Deus que cuida dos pássaros do céu e das flores do campo cuida de você com amor infinito. Confie nos planos dEle, pois são sempre de paz e esperança.

Que este dia seja uma oportunidade de renovar sua fé e caminhar com coragem em direção aos propósitos divinos para sua vida.`);
      setIsReflectionLoading(false);
    }, 2000);
  };

  return (
    <div className="min-h-screen bg-white">
      {/* Header */}
      <header className="fixed top-0 w-full bg-white shadow-md z-50">
        <div className="container mx-auto px-4 py-3 flex items-center justify-between">
          <div className="flex items-center">
            <img 
              src="https://i.imgur.com/zlIM5kM.jpeg" 
              alt="Logo AD Templo Arthur Bernardes" 
              className="h-12 w-auto"
            />
          </div>
          
          {/* Desktop Menu */}
          <nav className="hidden lg:flex items-center space-x-8">
            <MenuItem href="#inicio">Início</MenuItem>
            <MenuItem href="#programacao">Programação</MenuItem>
            <button
              onClick={() => setShowSchedule(true)}
              className="text-gray-700 hover:text-red-600 transition-colors duration-200 font-medium flex items-center gap-1"
            >
              <Calendar size={16} />
              Programação Semanal
            </button>
            <MenuItem href="#departamentos">Departamentos</MenuItem>
            <MenuItem href="#missoes">Missões</MenuItem>
            <MenuItem href="#sobre">Sobre Nós</MenuItem>
            <MenuItem href="#oracao">Oração & Reflexão</MenuItem>
            <MenuItem href="#contato">Contato</MenuItem>
            <button className="bg-red-600 text-white px-6 py-2 rounded-full hover:bg-red-700 transition-colors duration-200 font-semibold">
              Contribua
            </button>
          </nav>

          {/* Mobile Menu Button */}
          <button 
            className="lg:hidden text-gray-700 hover:text-red-600 transition-colors"
            onClick={toggleMenu}
          >
            {isMenuOpen ? <X size={24} /> : <Menu size={24} />}
          </button>
        </div>

        {/* Mobile Menu */}
        {isMenuOpen && (
          <div className="lg:hidden bg-white border-t">
            <nav className="container mx-auto px-4 py-4 space-y-4">
              <MenuItem href="#inicio" onClick={closeMenu}>Início</MenuItem>
              <MenuItem href="#programacao" onClick={closeMenu}>Programação</MenuItem>
              <button
                onClick={() => {
                  setShowSchedule(true);
                  closeMenu();
                }}
                className="w-full text-left text-gray-700 hover:text-red-600 transition-colors duration-200 font-medium flex items-center gap-2"
              >
                <Calendar size={16} />
                Programação Semanal
              </button>
              <MenuItem href="#departamentos" onClick={closeMenu}>Departamentos</MenuItem>
              <MenuItem href="#missoes" onClick={closeMenu}>Missões</MenuItem>
              <MenuItem href="#sobre" onClick={closeMenu}>Sobre Nós</MenuItem>
              <MenuItem href="#oracao" onClick={closeMenu}>Oração & Reflexão</MenuItem>
              <MenuItem href="#contato" onClick={closeMenu}>Contato</MenuItem>
              <button 
                className="w-full bg-red-600 text-white px-6 py-3 rounded-full hover:bg-red-700 transition-colors duration-200 font-semibold"
                onClick={closeMenu}
              >
                Contribua
              </button>
            </nav>
          </div>
        )}
      </header>

      {/* Hero Section */}
      <section id="inicio" className="relative h-screen flex items-center justify-center">
        <div 
          className="absolute inset-0 bg-cover bg-center"
          style={{ 
            backgroundImage: 'url(https://i.imgur.com/ANjRutY.jpeg)',
          }}
        />
        <div className="absolute inset-0 bg-black bg-opacity-50" />
        <div className="relative z-10 text-center text-white px-4">
          <h1 className="text-4xl md:text-6xl font-bold mb-6 leading-tight">
            Seja bem-vindo à nossa casa!
          </h1>
          <p className="text-xl md:text-2xl mb-8 max-w-2xl mx-auto leading-relaxed">
            Um lugar de fé, comunhão e esperança para toda a família.
          </p>
          <button 
            onClick={() => document.querySelector('#programacao')?.scrollIntoView({ behavior: 'smooth' })}
            className="bg-red-600 hover:bg-red-700 text-white px-8 py-4 rounded-full font-semibold text-lg transition-all duration-200 inline-flex items-center gap-2 hover:scale-105"
          >
            Nossa Programação
            <ChevronRight size={20} />
          </button>
        </div>
      </section>

      {/* Programação Section */}
      <section id="programacao" className="py-20 bg-gray-50">
        <div className="container mx-auto px-4">
          <h2 className="text-4xl font-bold text-center text-gray-800 mb-16">Nossa Programação</h2>
          <div className="grid md:grid-cols-3 gap-8">
            <ProgramCard
              title="Escola Bíblica Dominical"
              schedule="Aos domingos, às 8h"
              description="Momento de aprendizado da Palavra de Deus para toda a família, com classes divididas por idade e interesse."
              icon={<BookOpen size={40} />}
            />
            <ProgramCard
              title="Culto de Adoração e Louvor"
              schedule="Aos domingos, às 18:30"
              description="Culto principal da semana com adoração, pregação da Palavra e comunhão entre os irmãos."
              icon={<Heart size={40} />}
            />
            <ProgramCard
              title="Culto da Família"
              schedule="Às quartas-feiras, às 19:30"
              description="Um momento especial de oração, edificação e fortalecimento da fé para toda a família."
              icon={<Users size={40} />}
            />
          </div>
          
          <div className="text-center mt-12">
            <button
              onClick={() => setShowSchedule(true)}
              className="bg-red-600 hover:bg-red-700 text-white px-8 py-4 rounded-full font-semibold text-lg transition-all duration-200 inline-flex items-center gap-2 hover:scale-105"
            >
              <Calendar size={20} />
              Ver Programação Semanal Completa
            </button>
          </div>
        </div>
      </section>

      {/* Departamentos Section */}
      <section id="departamentos" className="py-20 bg-white">
        <div className="container mx-auto px-4">
          <h2 className="text-4xl font-bold text-center text-gray-800 mb-16">Nossos Departamentos</h2>
          <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <DepartmentCard
              name="Círculo de Oração"
              description="Grupo dedicado à intercessão e oração pelos membros da igreja e necessidades da comunidade."
              icon={<HandHeart />}
            />
            <DepartmentCard
              name="Mocidade (Jovens)"
              description="Ministério voltado para jovens, com atividades de discipulado, lazer e evangelismo."
              icon={<Users />}
            />
            <DepartmentCard
              name="Escola Bíblica Dominical"
              description="Departamento responsável pelo ensino sistemático da Bíblia para todas as idades."
              icon={<BookOpen />}
            />
            <DepartmentCard
              name="Ação Social"
              description="Ministério dedicado ao cuidado dos necessitados e obras sociais na comunidade."
              icon={<Heart />}
            />
          </div>
        </div>
      </section>

      {/* Missões Section */}
      <section id="missoes" className="py-20 bg-gray-50">
        <div className="container mx-auto px-4">
          <h2 className="text-4xl font-bold text-center text-gray-800 mb-16">Nossas Missões</h2>
          <div className="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-6">
            <DepartmentCard
              name="Homens"
              description="Ministério masculino focado no desenvolvimento espiritual e liderança cristã."
              icon={<Users />}
            />
            <DepartmentCard
              name="Mulheres"
              description="Ministério feminino com atividades de ensino, comunhão e apoio mútuo."
              icon={<Heart />}
            />
            <DepartmentCard
              name="Adolescentes"
              description="Ministério dedicado aos adolescentes, com atividades específicas para esta faixa etária."
              icon={<Users />}
            />
            <DepartmentCard
              name="Crianças"
              description="Ministério infantil com ensino bíblico adequado para as crianças da igreja."
              icon={<Heart />}
            />
            <DepartmentCard
              name="Estrangeiras"
              description="Missão dedicada ao evangelismo e plantação de igrejas em outras nações."
              icon={<MapPin />}
            />
          </div>
        </div>
      </section>

      {/* Sobre Nós Section */}
      <section id="sobre" className="py-20 bg-white">
        <div className="container mx-auto px-4">
          <div className="grid lg:grid-cols-2 gap-12 items-center max-w-6xl mx-auto">
            <div className="text-center lg:text-left">
              <img 
                src="https://i.imgur.com/WhJBrI7.png" 
                alt="Pastor Dionísio Maciel" 
                className="w-64 h-64 rounded-full mx-auto lg:mx-0 object-cover shadow-2xl"
              />
            </div>
            <div>
              <h2 className="text-4xl font-bold text-gray-800 mb-8">Uma Palavra do Nosso Pastor</h2>
              <div className="space-y-6 text-gray-600 leading-relaxed text-lg">
                <p>
                  É com grande alegria que recebemos você em nossa casa de oração. A Assembleia de Deus Templo Arthur Bernardes é um lugar onde famílias se reúnem para adorar ao Senhor e crescer na fé.
                </p>
                <p>
                  Nossa missão é proclamar o Evangelho de Cristo, edificar vidas através da Palavra de Deus e ser uma bênção para nossa comunidade. Aqui você encontrará um ambiente acolhedor, onde poderá experimentar o amor de Deus e desenvolver relacionamentos genuínos.
                </p>
                <p>
                  Venha fazer parte desta grande família! Estamos de braços abertos para recebê-lo e caminhar juntos nesta jornada de fé.
                </p>
                <p className="text-red-600 font-semibold text-xl mt-8">
                  - Pr. Dionísio Maciel
                </p>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* Mensagem em Áudio Section */}
      <section className="py-20 bg-gray-50">
        <div className="container mx-auto px-4">
          <h2 className="text-4xl font-bold text-center text-gray-800 mb-16">Mensagem em Áudio</h2>
          <div className="max-w-4xl mx-auto">
            <div className="bg-white p-8 rounded-lg shadow-lg">
              <iframe 
                width="100%" 
                height="300" 
                scrolling="no" 
                frameBorder="no" 
                allow="autoplay" 
                src="https://w.soundcloud.com/player/?url=https%3A//api.soundcloud.com/tracks/1915750859&color=%23c0392b&auto_play=false&hide_related=false&show_comments=true&show_user=true&show_reposts=false&show_teaser=true&visual=true"
                className="rounded-lg"
                title="Mensagem em Áudio - O que é ser Cristão"
              />
            </div>
          </div>
        </div>
      </section>

      {/* Oração & Reflexão Section */}
      <section id="oracao" className="py-20 bg-white">
        <div className="container mx-auto px-4">
          <h2 className="text-4xl font-bold text-center text-gray-800 mb-2">
            Oração e Reflexão
          </h2>
          <div className="text-center mb-16">
            <Sparkles className="inline-block text-red-600 ml-2" size={32} />
          </div>
          
          <div className="grid lg:grid-cols-2 gap-12 max-w-6xl mx-auto">
            {/* Gerador de Oração */}
            <div className="bg-gray-50 p-8 rounded-lg">
              <h3 className="text-2xl font-bold text-gray-800 mb-6 flex items-center gap-2">
                <Sparkles className="text-red-600" size={24} />
                Me Ajude a Orar
              </h3>
              <div className="space-y-4">
                <div>
                  <label htmlFor="prayer-topic" className="block text-sm font-medium text-gray-700 mb-2">
                    Sobre o que você gostaria de orar?
                  </label>
                  <input
                    id="prayer-topic"
                    type="text"
                    value={prayerTopic}
                    onChange={(e) => setPrayerTopic(e.target.value)}
                    placeholder="Ex: saúde, família, trabalho..."
                    className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent"
                  />
                </div>
                <button
                  onClick={generatePrayer}
                  disabled={isPrayerLoading || !prayerTopic.trim()}
                  className="w-full bg-red-600 text-white px-6 py-3 rounded-lg hover:bg-red-700 transition-colors duration-200 font-semibold flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  {isPrayerLoading ? (
                    <>
                      <div className="animate-spin rounded-full h-5 w-5 border-b-2 border-white"></div>
                      Gerando...
                    </>
                  ) : (
                    <>
                      <Sparkles size={20} />
                      Gerar Oração
                    </>
                  )}
                </button>
                {prayerResult && (
                  <div className="mt-6 p-4 bg-white border border-gray-200 rounded-lg">
                    <h4 className="font-semibold text-gray-800 mb-2">Sua Oração:</h4>
                    <p className="text-gray-600 leading-relaxed whitespace-pre-line">{prayerResult}</p>
                  </div>
                )}
              </div>
            </div>

            {/* Gerador de Reflexão */}
            <div className="bg-gray-50 p-8 rounded-lg">
              <h3 className="text-2xl font-bold text-gray-800 mb-6 flex items-center gap-2">
                <Sparkles className="text-red-600" size={24} />
                Reflexão do Dia
              </h3>
              <div className="space-y-4">
                <p className="text-gray-600">
                  Receba uma palavra de edificação e encorajamento para o seu dia.
                </p>
                <button
                  onClick={generateReflection}
                  disabled={isReflectionLoading}
                  className="w-full bg-red-600 text-white px-6 py-3 rounded-lg hover:bg-red-700 transition-colors duration-200 font-semibold flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  {isReflectionLoading ? (
                    <>
                      <div className="animate-spin rounded-full h-5 w-5 border-b-2 border-white"></div>
                      Gerando...
                    </>
                  ) : (
                    <>
                      <Sparkles size={20} />
                      Gerar Reflexão do Dia
                    </>
                  )}
                </button>
                {reflectionResult && (
                  <div className="mt-6 p-4 bg-white border border-gray-200 rounded-lg">
                    <h4 className="font-semibold text-gray-800 mb-2">Reflexão de Hoje:</h4>
                    <div className="text-gray-600 leading-relaxed">
                      {reflectionResult.split('**').map((part, index) => 
                        index % 2 === 1 ? <strong key={index}>{part}</strong> : part
                      )}
                    </div>
                  </div>
                )}
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* Contato Section */}
      <section id="contato" className="py-20 bg-gray-50">
        <div className="container mx-auto px-4">
          <h2 className="text-4xl font-bold text-center text-gray-800 mb-16">Visite-nos</h2>
          <div className="grid lg:grid-cols-2 gap-12 max-w-6xl mx-auto">
            {/* Informações de Contato */}
            <div className="space-y-8">
              <div>
                <h3 className="text-2xl font-bold text-gray-800 mb-6">Informações de Contato</h3>
                <div className="space-y-4">
                  <div className="flex items-start gap-3">
                    <MapPin className="text-red-600 mt-1" size={20} />
                    <div>
                      <p className="font-semibold text-gray-800">AD Templo Arthur Bernardes</p>
                      <p className="text-gray-600">Av. Arthur Bernardes, 218 - Pratinha</p>
                      <p className="text-gray-600">Belém - PA, 66816-000</p>
                      <p className="text-sm text-gray-500 mt-1">Próximo à Yamada</p>
                    </div>
                  </div>
                  
                  <div className="flex items-center gap-3">
                    <Mail className="text-red-600" size={20} />
                    <a 
                      href="mailto:assembleia218@gmail.com" 
                      className="text-gray-600 hover:text-red-600 transition-colors"
                    >
                      assembleia218@gmail.com
                    </a>
                  </div>
                </div>
              </div>

              <div>
                <h3 className="text-2xl font-bold text-gray-800 mb-4">Como Chegar</h3>
                <p className="text-gray-600 leading-relaxed">
                  Estamos localizados na Avenida Arthur Bernardes, próximo à Yamada, 
                  em uma região de fácil acesso. Venha nos visitar e fazer parte 
                  desta grande família!
                </p>
              </div>
            </div>

            {/* Mapa */}
            <div>
              <div className="bg-white p-4 rounded-lg shadow-lg">
                <iframe 
                  src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3988.6208825985656!2d-48.4822111!3d-1.3753469!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x92a489cc536c6d75%3A0xab6fa90edb58b4be!2sAssembleia%20de%20Deus%20Templo%20Arthur%20Bernardes!5e0!3m2!1spt-BR!2sbr!4v1640995200000!5m2!1spt-BR!2sbr"
                  width="100%" 
                  height="400" 
                  style={{ border: 0 }}
                  allowFullScreen
                  loading="lazy" 
                  referrerPolicy="no-referrer-when-downgrade"
                  className="rounded-lg"
                  title="Localização da AD Templo Arthur Bernardes"
                />
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* Footer */}
      <footer className="bg-gray-800 text-white py-12">
        <div className="container mx-auto px-4">
          <div className="text-center space-y-8">
            <div>
              <img 
                src="https://i.imgur.com/zlIM5kM.jpeg" 
                alt="Logo AD Templo Arthur Bernardes" 
                className="h-16 w-auto mx-auto mb-4"
              />
            </div>
            
            <div>
              <p className="text-gray-300 mb-2">
                © {new Date().getFullYear()} AD Templo Arthur Bernardes. Todos os direitos reservados.
              </p>
              <p className="text-gray-400 text-sm">
                Av. Arthur Bernardes, 218 - Pratinha, Belém - PA, 66816-000
              </p>
            </div>

            <div className="flex justify-center space-x-6">
              <a 
                href="https://www.facebook.com/profile.php?id=100066674486332" 
                target="_blank" 
                rel="noopener noreferrer"
                className="text-gray-400 hover:text-white transition-colors duration-200"
              >
                <Facebook size={24} />
              </a>
            </div>
          </div>
        </div>
      </footer>

      {/* Weekly Schedule Modal */}
      {showSchedule && (
        <WeeklySchedule onClose={() => setShowSchedule(false)} />
      )}
    </div>
  );
}

export default App;